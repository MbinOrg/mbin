<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ActivityPub\ImageDto;
use App\DTO\ActivityPub\VideoDto;
use App\DTO\ModeratorDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Image;
use App\Entity\Instance;
use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Exception\InstanceBannedException;
use App\Exception\InvalidApPostException;
use App\Exception\InvalidWebfingerException;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\MagazineFactory;
use App\Factory\UserFactory;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\UpdateActorMessage;
use App\Message\DeleteImageMessage;
use App\Message\DeleteUserMessage;
use App\Repository\ApActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\ImageRepository;
use App\Repository\InstanceRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\ApObjectExtractor;
use App\Service\ActivityPub\Webfinger\WebFinger;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use App\Utils\UrlUtils;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ActivityPubManager
{
    public function __construct(
        private readonly ApActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly UserManager $userManager,
        private readonly UserFactory $userFactory,
        private readonly MagazineManager $magazineManager,
        private readonly MagazineFactory $magazineFactory,
        private readonly MagazineRepository $magazineRepository,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly ImageRepository $imageRepository,
        private readonly ImageManagerInterface $imageManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonFactory $personFactory,
        private readonly SettingsManager $settingsManager,
        private readonly WebFingerFactory $webFingerFactory,
        private readonly MentionManager $mentionManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $apUpdateActorLimiter,
        private readonly EntryRepository $entryRepository,
        private readonly EntryManager $entryManager,
        private readonly RemoteInstanceManager $remoteInstanceManager,
        private readonly InstanceRepository $instanceRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getActorProfileId(ActivityPubActorInterface $actor): string
    {
        if ($actor instanceof User) {
            if (!$actor->apId) {
                return $this->personFactory->getActivityPubId($actor);
            }
        }

        // @todo blid webfinger
        return $actor->apProfileId;
    }

    public function findRemoteActor(string $actorUrl): ?User
    {
        return $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);
    }

    public function createCcFromBody(?string $body): array
    {
        $mentions = $this->mentionManager->extract($body) ?? [];

        $urls = [];
        foreach ($mentions as $handle) {
            try {
                $actor = $this->findActorOrCreate($handle);
            } catch (\Exception $e) {
                continue;
            }

            if (!$actor) {
                continue;
            }

            $urls[] = $actor->apProfileId ?? $this->urlGenerator->generate(
                'ap_user',
                ['username' => $actor->getUserIdentifier()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $urls;
    }

    /**
     * Find an existing actor or create a new one if the actor doesn't yet exists.
     *
     * @param ?string $actorUrlOrHandle actorUrlOrHandle actor URL or actor handle (could even be null)
     *
     * @return User|Magazine|null or Magazine or null on error
     *
     * @throws InvalidApPostException
     * @throws InvalidArgumentException
     * @throws InvalidWebfingerException
     */
    public function findActorOrCreate(?string $actorUrlOrHandle): User|Magazine|null
    {
        if (\is_null($actorUrlOrHandle)) {
            return null;
        }

        $this->logger->debug('[ActivityPubManager::findActorOrCreate] Cearching for actor at "{handle}"', ['handle' => $actorUrlOrHandle]);
        if (str_contains($actorUrlOrHandle, $this->settingsManager->get('KBIN_DOMAIN').'/m/')) {
            $magazine = str_replace('https://'.$this->settingsManager->get('KBIN_DOMAIN').'/m/', '', $actorUrlOrHandle);
            $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found magazine: "{magName}"', ['magName' => $magazine]);

            return $this->magazineRepository->findOneByName($magazine);
        }

        $actorUrl = $actorUrlOrHandle;
        if (false === filter_var($actorUrl, FILTER_VALIDATE_URL)) {
            if (!substr_count(ltrim($actorUrl, '@'), '@')) {
                $user = $this->userRepository->findOneBy(['username' => ltrim($actorUrl, '@')]);
                if ($user instanceof User) {
                    if ($user->apId && !$user->isDeleted && !$user->isSoftDeleted() && !$user->isTrashed() && (!$user->apFetchedAt || $user->apFetchedAt->modify('+1 hour') < (new \DateTime()))) {
                        $this->dispatchUpdateActor($user->apProfileId);
                    }

                    return $user;
                }
            }

            $actorUrl = $this->webfinger($actorUrl)->getProfileId();
        }

        if (\in_array(
            parse_url($actorUrl, PHP_URL_HOST),
            [$this->settingsManager->get('KBIN_DOMAIN'), 'localhost', '127.0.0.1']
        )) {
            $name = explode('/', $actorUrl);
            $name = end($name);

            $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found user: "{user}"', ['user' => $name]);

            return $this->userRepository->findOneBy(['username' => $name]);
        }

        // Check if the instance is banned
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            return null;
        }

        $user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);
        if (!$user) {
            // also try the public URL if it was not found by the profile id
            $user = $this->userRepository->findOneBy(['apPublicUrl' => $actorUrl]);
        }
        if ($user instanceof User) {
            $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found remote user for url: "{url}" in db', ['url' => $actorUrl]);
            if ($user->apId && !$user->isDeleted && !$user->isSoftDeleted() && !$user->isTrashed() && (!$user->apFetchedAt || $user->apFetchedAt->modify('+1 hour') < (new \DateTime()))) {
                $this->dispatchUpdateActor($user->apProfileId);
            }

            return $user;
        }

        $magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);
        if ($magazine instanceof Magazine) {
            $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found remote user for url: "{url}" in db', ['url' => $actorUrl]);
            if (!$magazine->isTrashed() && !$magazine->isSoftDeleted() && (!$magazine->apFetchedAt || $magazine->apFetchedAt->modify('+1 hour') < (new \DateTime()))) {
                $this->dispatchUpdateActor($magazine->apProfileId);
            }

            return $magazine;
        }

        $actor = $this->apHttpClient->getActorObject($actorUrl);
        // Check if actor isn't empty (not set/null/empty array/etc.) and check if actor type is set
        if (!empty($actor) && isset($actor['type'])) {
            // User (we don't make a distinction between bots with type Service as Lemmy does)
            if (\in_array($actor['type'], User::USER_TYPES)) {
                $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found remote user at "{url}"', ['url' => $actorUrl]);

                return $this->createUser($actorUrl);
            }

            // Magazine (Group)
            if ('Group' === $actor['type']) {
                $this->logger->debug('[ActivityPubManager::findActorOrCreate] Found remote magazine at "{url}"', ['url' => $actorUrl]);

                return $this->createMagazine($actorUrl);
            }

            if ('Tombstone' === $actor['type']) {
                // deleted actor
                if (null !== ($magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl])) && null !== $magazine->apId) {
                    $this->magazineManager->purge($magazine);
                    $this->logger->warning('[ActivityPubManager::findActorOrCreate] Got a tombstone for magazine {name} at {url}, deleting it', ['name' => $magazine->name, 'url' => $actorUrl]);
                } elseif (null !== ($user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl])) && null !== $user->apId) {
                    $this->bus->dispatch(new DeleteUserMessage($user->getId()));
                    $this->logger->warning('[ActivityPubManager::findActorOrCreate] Got a tombstone for user {name} at {url}, deleting it', ['name' => $user->username, 'url' => $actorUrl]);
                }
            }
        } else {
            $this->logger->debug("[ActivityPubManager::findActorOrCreate] Actor not found, actorUrl: $actorUrl");
        }

        return null;
    }

    public function dispatchUpdateActor(string $actorUrl)
    {
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            return;
        }
        $limiter = $this->apUpdateActorLimiter
            ->create($actorUrl)
            ->consume(1);

        if ($limiter->isAccepted()) {
            $this->bus->dispatch(new UpdateActorMessage($actorUrl));
        } else {
            $this->logger->debug(
                '[ActivityPubManager::dispatchUpdateActor] Not dispatching updating actor for {actor}: one has been dispatched recently',
                ['actor' => $actorUrl, 'retry' => $limiter->getRetryAfter()]
            );
        }
    }

    /**
     * Try to find an existing actor or create a new one if the actor doesn't yet exists.
     *
     * @param ?string $actorUrlOrHandle actor URL or handle (could even be null)
     *
     * @throws \LogicException when the returned actor is not a user or is null
     */
    public function findUserActorOrCreateOrThrow(?string $actorUrlOrHandle): User|Magazine
    {
        $object = $this->findActorOrCreate($actorUrlOrHandle);
        if (!$object) {
            throw new \LogicException("Could not find actor for 'object' property at: '$actorUrlOrHandle'");
        } elseif (!$object instanceof User) {
            throw new \LogicException("Could not find user actor for 'object' property at: '$actorUrlOrHandle'");
        }

        return $object;
    }

    public function webfinger(string $id): WebFinger
    {
        $this->logger->debug('[ActivityPubManager::webfinger] Fetching webfinger "{id}"', ['id' => $id]);

        if (false === filter_var($id, FILTER_VALIDATE_URL)) {
            $id = ltrim($id, '@');

            return $this->webFingerFactory->get($id);
        }

        $handle = $this->buildHandle($id);

        return $this->webFingerFactory->get($handle);
    }

    private function buildHandle(string $id): string
    {
        $port = !\is_null(parse_url($id, PHP_URL_PORT))
            ? ':'.parse_url($id, PHP_URL_PORT)
            : '';
        $apObj = $this->apHttpClient->getActorObject($id);
        if (!isset($apObj['preferredUsername'])) {
            throw new \InvalidArgumentException("webfinger from $id does not supply a valid user object");
        }

        return \sprintf(
            '%s@%s%s',
            $apObj['preferredUsername'],
            parse_url($id, PHP_URL_HOST),
            $port
        );
    }

    /**
     * Creates a new user.
     *
     * @param string $actorUrl actor URL
     *
     * @return ?User or null on error
     *
     * @throws InstanceBannedException
     */
    private function createUser(string $actorUrl): ?User
    {
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            throw new InstanceBannedException();
        }
        $webfinger = $this->webfinger($actorUrl);
        $dto = $this->userFactory->createDtoFromAp($actorUrl, $webfinger->getHandle());
        $this->userManager->create(
            $dto,
            false,
            false,
            preApprove: true,
        );

        if (method_exists($this->cache, 'invalidateTags')) {
            // clear markdown renders that are tagged with the handle of the user
            $tag = UrlUtils::getCacheKeyForMarkdownUserMention($dto->apId);
            $this->cache->invalidateTags([$tag]);
            $this->logger->debug('cleared cached items with tag {t}', ['t' => $tag]);
        }

        return $this->updateUser($actorUrl);
    }

    /**
     * Update existing user and return user object.
     *
     * @param string $actorUrl actor URL
     *
     * @return ?User or null on error (e.g. actor not found)
     */
    private function updateUser(string $actorUrl): ?User
    {
        $this->logger->info('[ActivityPubManager::updateUser] Updating user {name}', ['name' => $actorUrl]);
        $user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);

        if ($user->isDeleted || $user->isTrashed() || $user->isSoftDeleted()) {
            return $user;
        }

        $actor = $this->apHttpClient->getActorObject($actorUrl);
        if (!$actor || !\is_array($actor)) {
            return null;
        }

        if (isset($actor['type']) && 'Tombstone' === $actor['type'] && $user instanceof User) {
            $this->bus->dispatch(new DeleteUserMessage($user->getId()));

            return null;
        }

        // Check if actor isn't empty (not set/null/empty array/etc.)
        if (isset($actor['endpoints']['sharedInbox']) || isset($actor['inbox'])) {
            // Update the following user columns
            $user->type = $actor['type'] ?? 'Person';
            $user->apInboxUrl = $actor['endpoints']['sharedInbox'] ?? $actor['inbox'];
            $user->apDomain = parse_url($actor['id'], PHP_URL_HOST);
            $user->apFollowersUrl = $actor['followers'] ?? null;
            $user->apAttributedToUrl = $actor['attributedTo'] ?? null;
            $user->apPreferredUsername = $actor['preferredUsername'] ?? null;
            $user->apDiscoverable = $actor['discoverable'] ?? null;
            $user->apManuallyApprovesFollowers = $actor['manuallyApprovesFollowers'] ?? false;
            $user->apPublicUrl = $actor['url'] ?? $actorUrl;
            $user->apDeletedAt = null;
            $user->apTimeoutAt = null;
            $user->apFetchedAt = new \DateTime();

            if (isset($actor['published'])) {
                try {
                    $createdAt = new \DateTimeImmutable($actor['published']);
                    $now = new \DateTimeImmutable();
                    if ($createdAt < $now) {
                        $user->createdAt = $createdAt;
                    }
                } catch (\Exception) {
                }
            }

            // Only update about when summary is set
            if (isset($actor['summary'])) {
                $converter = new HtmlConverter(['strip_tags' => true]);
                $user->about = stripslashes($converter->convert($actor['summary']));
            }

            // Only update avatar if icon is set
            if (isset($actor['icon'])) {
                // we only have to wrap the property in an array if it is not already an array, though that is not that easy to determine
                // because each json object is an associative array -> each image has to have a 'type' property so use that to check it
                $icon = !\array_key_exists('type', $actor['icon']) ? $actor['icon'] : [$actor['icon']];
                $newImage = $this->handleImages($icon);
                if ($user->avatar && $newImage !== $user->avatar) {
                    $this->bus->dispatch(new DeleteImageMessage($user->avatar->getId()));
                }
                $user->avatar = $newImage;
            } elseif (null !== $user->avatar) {
                $this->bus->dispatch(new DeleteImageMessage($user->avatar->getId()));
                $user->avatar = null;
            }

            // Only update cover if image is set
            if (isset($actor['image'])) {
                // we only have to wrap the property in an array if it is not already an array, though that is not that easy to determine
                // because each json object is an associative array -> each image has to have a 'type' property so use that to check it
                $cover = !\array_key_exists('type', $actor['image']) ? $actor['image'] : [$actor['image']];
                $newImage = $this->handleImages($cover);
                if ($user->cover && $newImage !== $user->cover) {
                    $this->bus->dispatch(new DeleteImageMessage($user->cover->getId()));
                }
                $user->cover = $newImage;
            } elseif (null !== $user->cover) {
                $this->bus->dispatch(new DeleteImageMessage($user->cover->getId()));
                $user->cover = null;
            }

            if (isset($actor['publicKey']['publicKeyPem']) && $user->publicKey !== $actor['publicKey']['publicKeyPem']) {
                if (null !== $user->publicKey) {
                    // only log the message if there already was a public key. When initially created the actors do not get one
                    $this->logger->info('The public key of user "{u}" has changed', ['u' => $user->username]);
                    $user->lastKeyRotationDate = new \DateTime();
                }
                $user->oldPublicKey = $user->publicKey;
                $user->publicKey = $actor['publicKey']['publicKeyPem'];
            }

            if (null !== $user->apFollowersUrl) {
                try {
                    $followersObj = $this->apHttpClient->getCollectionObject($user->apFollowersUrl);
                    if (isset($followersObj['totalItems']) and \is_int($followersObj['totalItems'])) {
                        $user->apFollowersCount = $followersObj['totalItems'];
                        $user->updateFollowCounts();
                    }
                } catch (InvalidApPostException|InvalidArgumentException $ignored) {
                }
            }

            if (null !== $user->apId) {
                $instance = $this->instanceRepository->findOneBy(['domain' => $user->apDomain]);
                if (null === $instance) {
                    $instance = new Instance($user->apDomain);
                }
                $this->remoteInstanceManager->updateInstance($instance);
            }

            // Write to DB
            $this->entityManager->flush();

            return $user;
        } else {
            $this->logger->debug("[ActivityPubManager::updateUser] Actor not found, actorUrl: $actorUrl");
        }

        return null;
    }

    public function handleImages(array $attachment): ?Image
    {
        $images = array_filter(
            $attachment,
            fn ($val) => $this->isImageAttachment($val)
        ); // @todo multiple images

        if (\count($images)) {
            try {
                $imageObject = $images[array_key_first($images)];
                if (isset($imageObject['height'])) {
                    // determine the highest resolution image
                    foreach ($images as $i) {
                        if (isset($i['height']) && $i['height'] ?? 0 > $imageObject['height'] ?? 0) {
                            $imageObject = $i;
                        }
                    }
                }
                if ($tempFile = $this->imageManager->download($imageObject['url'])) {
                    $image = $this->imageRepository->findOrCreateFromPath($tempFile);
                    $image->sourceUrl = $imageObject['url'];
                    if ($image && isset($imageObject['name'])) {
                        $image->altText = $imageObject['name'];
                    }
                    $this->entityManager->persist($image);
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                return null;
            }

            return $image ?? null;
        }

        return null;
    }

    public static function extractUrlFromAttachment(mixed $attachment): ?string
    {
        $url = null;
        if (\is_array($attachment)) {
            $link = array_filter(
                $attachment,
                fn ($val) => 'Link' === $val['type']
            );

            $firstArrayKey = array_key_first($link);
            if (!empty($link[$firstArrayKey]) && isset($link[$firstArrayKey]['href']) && \is_string($link[$firstArrayKey]['href'])) {
                $url = $link[$firstArrayKey]['href'];
            } elseif (isset($link['href']) && \is_string($link['href'])) {
                $url = $link['href'];
            }
        }

        return $url;
    }

    /**
     * Creates a new magazine (Group).
     *
     * @param string $actorUrl actor URL
     *
     * @return ?Magazine or null on error
     *
     * @throws InstanceBannedException
     */
    private function createMagazine(string $actorUrl): ?Magazine
    {
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            throw new InstanceBannedException();
        }
        $dto = $this->magazineFactory->createDtoFromAp($actorUrl, $this->buildHandle($actorUrl));
        $this->magazineManager->create(
            $dto,
            null,
            false
        );

        try {
            if (method_exists($this->cache, 'invalidateTags')) {
                // clear markdown renders that are tagged with the handle of the magazine
                $tag = UrlUtils::getCacheKeyForMarkdownMagazineMention($dto->apId);
                $this->cache->invalidateTags([$tag]);
                $this->logger->debug('cleared cached items with tag {t}', ['t' => $tag]);
            }
        } catch (CacheException $ex) {
            $this->logger->error('An error occurred during cache clearing: {e} - {m}', ['e' => \get_class($ex), 'm' => $ex->getMessage()]);
        }

        return $this->updateMagazine($actorUrl);
    }

    /**
     * Update an existing magazine.
     *
     * @param string $actorUrl actor URL
     *
     * @return ?Magazine or null on error
     */
    private function updateMagazine(string $actorUrl): ?Magazine
    {
        $this->logger->info('[ActivityPubManager::updateMagazine] Updating magazine "{magName}"', ['magName' => $actorUrl]);
        $magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);

        if ($magazine->isTrashed() || $magazine->isSoftDeleted()) {
            return $magazine;
        }

        $actor = $this->apHttpClient->getActorObject($actorUrl);
        // Check if actor isn't empty (not set/null/empty array/etc.)

        if ($actor && 'Tombstone' === $actor['type'] && $magazine instanceof Magazine && null !== $magazine->apId) {
            // tombstone for remote magazine -> delete it
            $this->magazineManager->purge($magazine);

            return null;
        }

        if (isset($actor['endpoints']['sharedInbox']) || isset($actor['inbox'])) {
            if (isset($actor['summary'])) {
                $magazine->description = $this->extractMarkdownSummary($actor);
            }

            if (isset($actor['icon'])) {
                // we only have to wrap the property in an array if it is not already an array, though that is not that easy to determine
                // because each json object is an associative array -> each image has to have a 'type' property so use that to check it
                $icon = !\array_key_exists('type', $actor['icon']) ? $actor['icon'] : [$actor['icon']];
                $newImage = $this->handleImages($icon);
                if ($magazine->icon && $newImage !== $magazine->icon) {
                    $this->bus->dispatch(new DeleteImageMessage($magazine->icon->getId()));
                }
                $magazine->icon = $newImage;
            } elseif (null !== $magazine->icon) {
                $this->bus->dispatch(new DeleteImageMessage($magazine->icon->getId()));
                $magazine->icon = null;
            }

            if (isset($actor['image'])) {
                $banner = !\array_key_exists('type', $actor['image']) ? $actor['image'] : [$actor['image']];
                $newImage = $this->handleImages($banner);
                if ($magazine->banner && $newImage !== $magazine->banner) {
                    $this->bus->dispatch(new DeleteImageMessage($magazine->banner->getId()));
                }
                $magazine->banner = $newImage;
            } elseif (null !== $magazine->banner) {
                $this->bus->dispatch(new DeleteImageMessage($magazine->banner->getId()));
                $magazine->banner = null;
            }

            if ($actor['name']) {
                $magazine->title = $actor['name'];
            } elseif ($actor['preferredUsername']) {
                $magazine->title = $actor['preferredUsername'];
            }

            if (isset($actor['published'])) {
                try {
                    $createdAt = new \DateTimeImmutable($actor['published']);
                    $now = new \DateTimeImmutable();
                    if ($createdAt < $now) {
                        $magazine->createdAt = $createdAt;
                    }
                } catch (\Exception) {
                }
            }

            $magazine->apInboxUrl = $actor['endpoints']['sharedInbox'] ?? $actor['inbox'];
            $magazine->apDomain = parse_url($actor['id'], PHP_URL_HOST);
            $magazine->apFollowersUrl = $actor['followers'] ?? null;
            $magazine->apAttributedToUrl = isset($actor['attributedTo']) && \is_string($actor['attributedTo']) ? $actor['attributedTo'] : null;
            $magazine->apFeaturedUrl = $actor['featured'] ?? null;
            $magazine->apPreferredUsername = $actor['preferredUsername'] ?? null;
            $magazine->apDiscoverable = $actor['discoverable'] ?? null;
            $magazine->apPublicUrl = $actor['url'] ?? $actorUrl;
            $magazine->apDeletedAt = null;
            $magazine->apTimeoutAt = null;
            $magazine->apFetchedAt = new \DateTime();
            $magazine->isAdult = $actor['sensitive'] ?? false;
            $magazine->postingRestrictedToMods = filter_var($actor['postingRestrictedToMods'] ?? false, FILTER_VALIDATE_BOOLEAN) ?? false;

            if (null !== $magazine->apFollowersUrl) {
                try {
                    $this->logger->debug('[ActivityPubManager::updateMagazine] Updating remote followers of magazine "{magUrl}"', ['magUrl' => $actorUrl]);
                    $followersObj = $this->apHttpClient->getCollectionObject($magazine->apFollowersUrl);
                    if (isset($followersObj['totalItems']) and \is_int($followersObj['totalItems'])) {
                        $magazine->apFollowersCount = $followersObj['totalItems'];
                        $magazine->updateSubscriptionsCount();
                    }
                } catch (InvalidApPostException|InvalidArgumentException $ignored) {
                }
            }

            if (null !== $magazine->apAttributedToUrl) {
                try {
                    $this->handleModeratorCollection($actorUrl, $magazine);
                } catch (InvalidArgumentException $ignored) {
                }
            } elseif (isset($actor['attributedTo']) && \is_array($actor['attributedTo'])) {
                $this->handleModeratorArray($magazine, $this->getActorFromAttributedTo($actor['attributedTo']));
            }

            if (null !== $magazine->apFeaturedUrl) {
                try {
                    $this->handleMagazineFeaturedCollection($actorUrl, $magazine);
                } catch (InvalidArgumentException $ignored) {
                }
            }

            if (isset($actor['publicKey']['publicKeyPem']) && $magazine->publicKey !== $actor['publicKey']['publicKeyPem']) {
                if (null !== $magazine->publicKey) {
                    // only log the message if there already was a public key. When initially created the actors do not get one
                    $this->logger->info('The public key of magazine "{m}" has changed', ['m' => $magazine->name]);
                    $magazine->lastKeyRotationDate = new \DateTime();
                }
                $magazine->oldPublicKey = $magazine->publicKey;
                $magazine->publicKey = $actor['publicKey']['publicKeyPem'];
            }

            if (null !== $magazine->apId) {
                $instance = $this->instanceRepository->findOneBy(['domain' => $magazine->apDomain]);
                if (null === $instance) {
                    $instance = new Instance($magazine->apDomain);
                }
                $this->remoteInstanceManager->updateInstance($instance);
            }
            $this->entityManager->flush();

            return $magazine;
        } else {
            $this->logger->debug("[ActivityPubManager::updateMagazine] Actor not found, actorUrl: $actorUrl");
        }

        return null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleModeratorCollection(string $actorUrl, Magazine $magazine): void
    {
        try {
            $this->logger->debug('[ActivityPubManager::handleModeratorCollection] Fetching moderators of remote magazine: "{magUrl}"', ['magUrl' => $actorUrl]);
            $attributedObj = $this->apHttpClient->getCollectionObject($magazine->apAttributedToUrl);
            $items = null;
            if (isset($attributedObj['items']) and \is_array($attributedObj['items'])) {
                $items = $attributedObj['items'];
            } elseif (isset($attributedObj['orderedItems']) and \is_array($attributedObj['orderedItems'])) {
                $items = $attributedObj['orderedItems'];
            }

            $this->logger->debug('[ActivityPubManager::handleModeratorCollection] Got moderator items for magazine: "{magName}": {json}', ['magName' => $magazine->name, 'json' => json_encode($attributedObj)]);

            if (null !== $items) {
                $this->handleModeratorArray($magazine, $items);
            } else {
                $this->logger->warning('[ActivityPubManager::handleModeratorCollection] Could not update the moderators of "{url}", the response doesn\'t have a "items" or "orderedItems" property or it is not an array', ['url' => $actorUrl]);
            }
        } catch (InvalidApPostException $ignored) {
        }
    }

    private function handleModeratorArray(Magazine $magazine, array $items): void
    {
        $moderatorsToRemove = [];
        /** @var Moderator $mod */
        foreach ($magazine->moderators as $mod) {
            $moderatorsToRemove[] = $mod->user;
        }
        $indexesNotToRemove = [];

        foreach ($items as $item) {
            if (\is_string($item)) {
                try {
                    $user = $this->findActorOrCreate($item);
                    if ($user instanceof User) {
                        foreach ($moderatorsToRemove as $key => $existMod) {
                            if ($existMod->username === $user->username) {
                                $indexesNotToRemove[] = $key;
                                break;
                            }
                        }
                        if (!$magazine->userIsModerator($user)) {
                            $this->logger->info('[ActivityPubManager::handleModeratorArray] Adding "{user}" as moderator in "{magName}" because they are a mod upstream, but not locally', ['user' => $user->username, 'magName' => $magazine->name]);
                            $this->magazineManager->addModerator(new ModeratorDto($magazine, $user, null));
                        }
                    }
                } catch (\Exception) {
                    $this->logger->warning('[ActivityPubManager::handleModeratorArray] Something went wrong while fetching actor "{actor}" as moderator of "{magName}"', ['actor' => $item, 'magName' => $magazine->name]);
                }
            }
        }

        foreach ($indexesNotToRemove as $i) {
            $moderatorsToRemove[$i] = null;
        }

        foreach ($moderatorsToRemove as $modToRemove) {
            if (null === $modToRemove) {
                continue;
            }
            $criteria = Criteria::create()->where(Criteria::expr()->eq('magazine', $magazine));
            $modObject = $modToRemove->moderatorTokens->matching($criteria)->first();
            $this->logger->info('[ActivityPubManager::handleModeratorArray] Removing "{exMod}" from "{magName}" as mod locally because they are no longer mod upstream', ['exMod' => $modToRemove->username, 'magName' => $magazine->name]);
            $this->magazineManager->removeModerator($modObject, null);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleMagazineFeaturedCollection(string $actorUrl, Magazine $magazine): void
    {
        try {
            $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Fetching featured posts of remote magazine: {url}', ['url' => $actorUrl]);
            $attributedObj = $this->apHttpClient->getCollectionObject($magazine->apFeaturedUrl);
            $items = null;
            if (isset($attributedObj['items']) and \is_array($attributedObj['items'])) {
                $items = $attributedObj['items'];
            } elseif (isset($attributedObj['orderedItems']) and \is_array($attributedObj['orderedItems'])) {
                $items = $attributedObj['orderedItems'];
            }

            $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Got featured items for magazine: "{magName}": {json}', ['magName' => $magazine->name, 'json' => json_encode($attributedObj)]);

            if (null !== $items) {
                $pinnedToRemove = $this->entryRepository->findPinned($magazine);
                $indexesNotToRemove = [];
                $idsToPin = [];
                foreach ($items as $item) {
                    $apId = null;
                    $isString = false;
                    if (\is_string($item)) {
                        $apId = $item;
                        $isString = true;
                    } elseif (\is_array($item)) {
                        $apId = $item['id'];
                    } else {
                        $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Ignoring {item}, because it is not a string and not an array', ['item' => json_encode($item)]);
                        continue;
                    }

                    $entry = null;

                    $alreadyPinned = false;
                    if ($this->settingsManager->isLocalUrl($apId)) {
                        $pair = $this->activityRepository->findLocalByApId($apId);
                        if (Entry::class === $pair['type']) {
                            foreach ($pinnedToRemove as $i => $entry) {
                                if ($entry->getId() === $pair['id']) {
                                    $indexesNotToRemove[] = $i;
                                    $alreadyPinned = true;
                                }
                            }
                        }
                    } else {
                        foreach ($pinnedToRemove as $i => $entry) {
                            if ($entry->apId === $apId) {
                                $indexesNotToRemove[] = $i;
                                $alreadyPinned = true;
                            }
                        }

                        if (!$alreadyPinned) {
                            $existingEntry = $this->entryRepository->findOneBy(['apId' => $apId]);
                            if ($existingEntry) {
                                $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Pinning existing entry: {title}', ['title' => $existingEntry->title]);
                                $this->entryManager->pin($existingEntry, null);
                            } else {
                                if (!$this->settingsManager->isBannedInstance($apId)) {
                                    $object = $item;
                                    if ($isString) {
                                        $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Getting {url} because we dont have it', ['url' => $apId]);
                                        $object = $this->apHttpClient->getActivityObject($apId);
                                    }
                                    $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Dispatching create message for entry: {e}', ['e' => json_encode($object)]);
                                    $this->bus->dispatch(new CreateMessage($object, true));
                                } else {
                                    $this->logger->info('[ActivityPubManager::handleMagazineFeaturedCollection] The instance is banned, url: {url}', ['url' => $apId]);
                                }
                            }
                        }
                    }
                }

                foreach ($indexesNotToRemove as $i) {
                    $pinnedToRemove[$i] = null;
                }

                foreach (array_filter($pinnedToRemove) as $pinnedEntry) {
                    // the pin method also unpins if the entry is already pinned
                    $this->logger->debug('[ActivityPubManager::handleMagazineFeaturedCollection] Unpinning entry: "{title}"', ['title' => $pinnedEntry->title]);
                    $this->entryManager->pin($pinnedEntry, null);
                }
            }
        } catch (InvalidApPostException $ignored) {
        }
    }

    public function createInboxesFromCC(array $activity, User $user): array
    {
        $followersUrl = $this->urlGenerator->generate(
            'ap_user_followers',
            ['username' => $user->username],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $arr = array_unique(
            array_filter(
                array_merge(
                    \App\Utils\JsonldUtils::getArrayValue($activity, 'cc'),
                    \App\Utils\JsonldUtils::getArrayValue($activity, 'to'),
                ),
                fn ($val) => !\in_array($val, [ActivityPubActivityInterface::PUBLIC_URL, $followersUrl, []])
            )
        );

        $users = [];
        foreach ($arr as $url) {
            if ($user = $this->findActorOrCreate($url)) {
                $users[] = $user;
            }
        }

        return array_map(fn ($user) => $user->apInboxUrl, $users);
    }

    public function handleVideos(array $attachment): ?VideoDto
    {
        $videos = array_filter(
            $attachment,
            fn ($val) => \in_array($val['type'], ['Document', 'Video']) && VideoManager::isVideoUrl($val['url'])
        );

        if (\count($videos)) {
            return (new VideoDto())->create(
                $videos[0]['url'],
                $videos[0]['mediaType'],
                !empty($videos['0']['name']) ? $videos['0']['name'] : $videos['0']['mediaType']
            );
        }

        return null;
    }

    public function handleExternalImages(array $attachment): ?array
    {
        $images = array_filter(
            $attachment,
            fn ($val) => $this->isImageAttachment($val)
        );

        array_shift($images);

        if (\count($images)) {
            return array_map(fn ($val) => (new ImageDto())->create(
                $val['url'],
                $val['mediaType'],
                !empty($val['name']) ? $val['name'] : $val['mediaType']
            ), $images);
        }

        return null;
    }

    public function handleExternalVideos(array $attachment): ?array
    {
        $videos = array_filter(
            $attachment,
            fn ($val) => \in_array($val['type'], ['Document', 'Video']) && VideoManager::isVideoUrl($val['url'])
        );

        if (\count($videos)) {
            return array_map(fn ($val) => (new VideoDto())->create(
                $val['url'],
                $val['mediaType'],
                !empty($val['name']) ? $val['name'] : $val['mediaType']
            ), $videos);
        }

        return null;
    }

    /**
     * Update existing actor.
     *
     * @param string $actorUrl actor URL
     *
     * @return Magazine|User|null null on error
     */
    public function updateActor(string $actorUrl): Magazine|User|null
    {
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            return null;
        }

        if ($this->userRepository->findOneBy(['apProfileId' => $actorUrl])) {
            return $this->updateUser($actorUrl);
        } elseif ($this->magazineRepository->findOneBy(['apProfileId' => $actorUrl])) {
            return $this->updateMagazine($actorUrl);
        }

        return null;
    }

    public function findOrCreateMagazineByToCCAndAudience(array $object): ?Magazine
    {
        $potentialGroups = self::getReceivers($object);
        $magazine = $this->magazineRepository->findByApGroupProfileId($potentialGroups);
        if ($magazine and $magazine->apId && !$magazine->isTrashed() && !$magazine->isSoftDeleted() && (!$magazine->apFetchedAt || $magazine->apFetchedAt->modify('+1 Day') < (new \DateTime()))) {
            $this->dispatchUpdateActor($magazine->apPublicUrl);
        }

        if (null === $magazine) {
            foreach ($potentialGroups as $potentialGroup) {
                $result = $this->findActorOrCreate($potentialGroup);
                if ($result instanceof Magazine) {
                    $magazine = $result;
                    break;
                }
            }
        }

        if (null === $magazine) {
            $magazine = $this->magazineRepository->findOneByName('random');
        }

        return $magazine;
    }

    public static function getReceivers(array $object): array
    {
        $res = array_merge(
            \App\Utils\JsonldUtils::getArrayValue($object, 'audience'),
            \App\Utils\JsonldUtils::getArrayValue($object, 'to'),
            \App\Utils\JsonldUtils::getArrayValue($object, 'cc'),
        );

        if (isset($object['object']) and \is_array($object['object'])) {
            $res = array_merge(
                $res,
                \App\Utils\JsonldUtils::getArrayValue($object['object'], 'audience'),
                \App\Utils\JsonldUtils::getArrayValue($object['object'], 'to'),
                \App\Utils\JsonldUtils::getArrayValue($object['object'], 'cc'),
            );
        } elseif (isset($object['attributedTo']) && \is_array($object['attributedTo'])) {
            // if there is no "object" inside of this it will probably be a create activity which has an attributedTo field
            // this was implemented for peertube support, because they list the channel (Group) and the user in an array in that field
            $groups = array_filter($object['attributedTo'], fn ($item) => \is_array($item) && !empty($item['type']) && 'Group' === $item['type']);
            $res = array_merge($res, array_map(fn ($item) => $item['id'], $groups));
        }

        $res = array_filter($res, fn ($i) => null !== $i and ActivityPubActivityInterface::PUBLIC_URL !== $i);

        return array_unique($res);
    }

    private function isImageAttachment(array $object): bool
    {
        // attachment object has acceptable object type
        if (!\in_array($object['type'], ['Document', 'Image'])) {
            return false;
        }

        // attachment is either:
        // - has `mediaType` field and is a recognized image types
        // - image url looks like a link to image
        return (!empty($object['mediaType']) && ImageManager::isImageType($object['mediaType']))
            || ImageManager::isImageUrl($object['url']);
    }

    /**
     * @param string|array                                       $apObject      the object that should be like, so a post of any kind in its AP array representation or a URL
     * @param array                                              $fullPayload   the full message payload, only used to log it
     * @param callable(array $object, ?string $adjustedUrl):void $chainDispatch if we do not have the object in our db this is called to dispatch a new ChainActivityMessage.
     *                                                                          Since the explicit object has to be set in the message this has to be done as a callback method.
     *                                                                          The object parameter is an associative array representing the first dependency of the activity.
     *                                                                          The $adjustedUrl parameter is only set if the object was fetched from a different url than the id of the object might suggest
     *
     * @see ChainActivityMessage
     */
    public function getEntityObject(string|array $apObject, array $fullPayload, callable $chainDispatch): Entry|EntryComment|Post|PostComment|null
    {
        $object = null;
        $activity = null;
        $calledUrl = null;
        if (\is_string($apObject)) {
            if (false === filter_var($apObject, FILTER_VALIDATE_URL)) {
                $this->logger->error('[ActivityPubManager::getEntityObject] The like activity references an object by string, but that is not a URL, discarding the message', $fullPayload);

                return null;
            }
            // First try to find the activity object in our database
            $activity = $this->activityRepository->findByObjectId($apObject);
            $calledUrl = $apObject;
            if (!$activity) {
                if (!$this->settingsManager->isBannedInstance($apObject)) {
                    $this->logger->debug('[ActivityPubManager::getEntityObject] Object is fetched from {url} because it is a string and could not be found in our repo', ['url' => $apObject]);
                    $object = $this->apHttpClient->getActivityObject($apObject);
                } else {
                    $this->logger->info('[ActivityPubManager::getEntityObject] The instance is banned, url: {url}', ['url' => $apObject]);

                    return null;
                }
            }
        } else {
            $activity = $this->activityRepository->findByObjectId($apObject['id']);
            $calledUrl = $apObject['id'];
            if (!$activity) {
                $this->logger->debug('[ActivityPubManager::getEntityObject] Object is fetched from {url} because it is not a string and could not be found in our repo', ['url' => $apObject['id']]);
                $object = $apObject;
            }
        }

        if (!$activity && !$object) {
            $this->logger->error("[ActivityPubManager::getEntityObject] The activity is still null and we couldn't get the object from the url, discarding", $fullPayload);

            return null;
        }

        if ($object) {
            $adjustedUrl = null;
            if ($object['id'] !== $calledUrl) {
                $this->logger->warning('[ActivityPubManager::getEntityObject] The url {url} returned a different object id: {id}', ['url' => $calledUrl, 'id' => $object['id']]);
                $adjustedUrl = $object['id'];
            }

            $this->logger->debug('[ActivityPubManager::getEntityObject] Dispatching a ChainActivityMessage, because the object could not be found: {o}', ['o' => $apObject]);
            $this->logger->debug('[ActivityPubManager::getEntityObject] The object for ChainActivityMessage with object {o}', ['o' => $object]);
            $chainDispatch($object, $adjustedUrl);

            return null;
        }

        return $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
    }

    public function extractMarkdownSummary(array $apObject): ?string
    {
        if (isset($apObject['source']) && isset($apObject['source']['mediaType']) && isset($apObject['source']['content']) && ApObjectExtractor::MARKDOWN_TYPE === $apObject['source']['mediaType']) {
            return $apObject['source']['content'];
        } else {
            $converter = new HtmlConverter(['strip_tags' => true]);

            return stripslashes($converter->convert($apObject['summary']));
        }
    }

    public function extractMarkdownContent(array $apObject)
    {
        if (isset($apObject['source']) && isset($apObject['source']['mediaType']) && isset($apObject['source']['content']) && ApObjectExtractor::MARKDOWN_TYPE === $apObject['source']['mediaType']) {
            return $apObject['source']['content'];
        } else {
            $converter = new HtmlConverter(['strip_tags' => true]);

            return stripslashes($converter->convert($apObject['content']));
        }
    }

    public function isActivityPublic(array $payload): bool
    {
        $to = array_merge(
            \App\Utils\JsonldUtils::getArrayValue($payload, 'to'),
            \App\Utils\JsonldUtils::getArrayValue($payload, 'cc'),
        );

        foreach ($to as $receiver) {
            $id = null;
            if (\is_string($receiver)) {
                $id = $receiver;
            } elseif (\is_array($receiver) && !empty($receiver['id'])) {
                $id = $receiver['id'];
            }

            if (null !== $id) {
                $actor = $this->findActorOrCreate($id);
                if ($actor instanceof Magazine) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getSingleActorFromAttributedTo(string|array|null $attributedTo, bool $filterForPerson = true): ?string
    {
        $actors = $this->getActorFromAttributedTo($attributedTo, $filterForPerson);
        if (\sizeof($actors) > 0) {
            return $actors[0];
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getActorFromAttributedTo(string|array|null $attributedTo, bool $filterForPerson = true): array
    {
        if (\is_string($attributedTo)) {
            return [$attributedTo];
        } elseif (\is_array($attributedTo)) {
            $actors = array_filter($attributedTo, fn ($item) => \is_string($item) || (\is_array($item) && !empty($item['type']) && (!$filterForPerson || 'Person' === $item['type'])));

            return array_map(fn ($item) => $item['id'], $actors);
        }

        return [];
    }

    public function extractUrl(string|array|null $url): ?string
    {
        if (\is_string($url)) {
            return $url;
        } elseif (\is_array($url)) {
            $urls = array_filter($url, fn ($item) => \is_string($item) || (\is_array($item) && !empty($item['type']) && 'Link' === $item['type'] && (empty($item['mediaType']) || 'text/html' === $item['mediaType'])));
            if (\sizeof($urls) >= 1) {
                if (\is_string($urls[0])) {
                    return $urls[0];
                } elseif (!empty($urls[0]['href'])) {
                    return $urls[0]['href'];
                }
            }
        }

        return null;
    }

    public function extractTotalAmountFromCollection(mixed $collection): ?int
    {
        $id = null;
        if (\is_string($collection)) {
            if (false !== filter_var($collection, FILTER_VALIDATE_URL)) {
                $id = $collection;
            }
        } elseif (\is_array($collection)) {
            if (isset($collection['totalItems'])) {
                return \intval($collection['totalItems']);
            } elseif (isset($collection['id'])) {
                $id = $collection['id'];
            }
        }

        if ($id) {
            $this->apHttpClient->invalidateCollectionObjectCache($id);
            $collection = $this->apHttpClient->getCollectionObject($id);
            if (isset($collection['totalItems']) && \is_int($collection['totalItems'])) {
                return $collection['totalItems'];
            }
        }

        return null;
    }

    public function extractRemoteLikeCount(array $apObject): ?int
    {
        if (!empty($apObject['likes'])) {
            return $this->extractTotalAmountFromCollection($apObject['likes']);
        }

        return null;
    }

    public function extractRemoteDislikeCount(array $apObject): ?int
    {
        if (!empty($apObject['dislikes'])) {
            return $this->extractTotalAmountFromCollection($apObject['dislikes']);
        }

        return null;
    }

    public function extractRemoteShareCount(array $apObject): ?int
    {
        if (!empty($apObject['shares'])) {
            return $this->extractTotalAmountFromCollection($apObject['shares']);
        }

        return null;
    }
}

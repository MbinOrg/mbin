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
use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\MagazineFactory;
use App\Factory\UserFactory;
use App\Message\ActivityPub\UpdateActorMessage;
use App\Message\DeleteImageMessage;
use App\Message\DeleteUserMessage;
use App\Repository\ApActivityRepository;
use App\Repository\ImageRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\ApObjectExtractor;
use App\Service\ActivityPub\Webfinger\WebFinger;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        private readonly ApHttpClient $apHttpClient,
        private readonly ImageRepository $imageRepository,
        private readonly ImageManager $imageManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonFactory $personFactory,
        private readonly SettingsManager $settingsManager,
        private readonly WebFingerFactory $webFingerFactory,
        private readonly MentionManager $mentionManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $apUpdateActorLimiter,
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

    public function createCcFromBody(string $body): array
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
     */
    public function findActorOrCreate(?string $actorUrlOrHandle): null|User|Magazine
    {
        if (\is_null($actorUrlOrHandle)) {
            return null;
        }

        $this->logger->debug('ActivityPubManager:findActorOrCreate: searching for actor at "{handle}"', ['handle' => $actorUrlOrHandle]);
        if (str_contains($actorUrlOrHandle, $this->settingsManager->get('KBIN_DOMAIN').'/m/')) {
            $magazine = str_replace('https://'.$this->settingsManager->get('KBIN_DOMAIN').'/m/', '', $actorUrlOrHandle);
            $this->logger->debug('found magazine "{magName}"', ['magName' => $magazine]);

            return $this->magazineRepository->findOneByName($magazine);
        }

        $actorUrl = $actorUrlOrHandle;
        if (false === filter_var($actorUrl, FILTER_VALIDATE_URL)) {
            if (!substr_count(ltrim($actorUrl, '@'), '@')) {
                $user = $this->userRepository->findOneBy(['username' => ltrim($actorUrl, '@')]);
                if ($user instanceof User) {
                    if ($user->apId && (!$user->apFetchedAt || $user->apFetchedAt->modify('+1 hour') < (new \DateTime()))) {
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

            $this->logger->debug('found user "{user}"', ['user' => $name]);

            return $this->userRepository->findOneBy(['username' => $name]);
        }

        $user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);
        if ($user instanceof User) {
            $this->logger->debug('found remote user for url "{url}" in db', ['url' => $actorUrl]);
            if ($user->apId && (!$user->apFetchedAt || $user->apFetchedAt->modify('+1 hour') < (new \DateTime()))) {
                $this->dispatchUpdateActor($user->apProfileId);
            }

            return $user;
        }
        $magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);
        if ($magazine instanceof Magazine) {
            $this->logger->debug('found remote user for url "{url}" in db', ['url' => $actorUrl]);
            if (!$magazine->apFetchedAt || $magazine->apFetchedAt->modify('+1 hour') < (new \DateTime())) {
                $this->dispatchUpdateActor($magazine->apProfileId);
            }

            return $magazine;
        }

        $actor = $this->apHttpClient->getActorObject($actorUrl);
        // Check if actor isn't empty (not set/null/empty array/etc.) and check if actor type is set
        if (!empty($actor) && isset($actor['type'])) {
            // User (we don't make a distinction between bots with type Service as Lemmy does)
            if (\in_array($actor['type'], User::USER_TYPES)) {
                $this->logger->debug('found remote user at "{url}"', ['url' => $actorUrl]);

                return $this->createUser($actorUrl);
            }

            // Magazine (Group)
            if ('Group' === $actor['type']) {
                $this->logger->debug('found remote magazine at "{url}"', ['url' => $actorUrl]);

                return $this->createMagazine($actorUrl);
            }

            if ('Tombstone' === $actor['type']) {
                // deleted actor
                if (null !== ($magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl])) && null !== $magazine->apId) {
                    $this->magazineManager->purge($magazine);
                    $this->logger->warning('got a tombstone for magazine {name} at {url}, deleting it', ['name' => $magazine->name, 'url' => $actorUrl]);
                } elseif (null !== ($user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl])) && null !== $user->apId) {
                    $this->bus->dispatch(new DeleteUserMessage($user->getId()));
                    $this->logger->warning('got a tombstone for user {name} at {url}, deleting it', ['name' => $user->username, 'url' => $actorUrl]);
                }
            }
        } else {
            $this->logger->debug("ActivityPubManager:findActorOrCreate:actorUrl: $actorUrl. Actor not found.");
        }

        return null;
    }

    public function dispatchUpdateActor(string $actorUrl)
    {
        $limiter = $this->apUpdateActorLimiter
            ->create($actorUrl)
            ->consume(1);

        if ($limiter->isAccepted()) {
            $this->bus->dispatch(new UpdateActorMessage($actorUrl));
        } else {
            $this->logger->debug(
                'not dispatching updating actor for {actor}: one has been dispatched recently',
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
        $this->logger->debug('fetching webfinger "{id}"', ['id' => $id]);

        if (false === filter_var($id, FILTER_VALIDATE_URL)) {
            $id = ltrim($id, '@');

            return $this->webFingerFactory->get($id);
        }

        $handle = $this->buildHandle($id);

        return $this->webFingerFactory->get($handle);
    }

    public function buildHandle(string $id): string
    {
        $port = !\is_null(parse_url($id, PHP_URL_PORT))
            ? ':'.parse_url($id, PHP_URL_PORT)
            : '';

        return sprintf(
            '%s@%s%s',
            $this->apHttpClient->getActorObject($id)['preferredUsername'],
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
     */
    private function createUser(string $actorUrl): ?User
    {
        $webfinger = $this->webfinger($actorUrl);
        $this->userManager->create(
            $this->userFactory->createDtoFromAp($actorUrl, $webfinger->getHandle()),
            false,
            false
        );

        return $this->updateUser($actorUrl);
    }

    /**
     * Update existing user and return user object.
     *
     * @param string $actorUrl actor URL
     *
     * @return ?User or null on error (e.g. actor not found)
     */
    public function updateUser(string $actorUrl): ?User
    {
        $this->logger->info('updating user {name}', ['name' => $actorUrl]);
        $user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);

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
            $user->apDiscoverable = $actor['discoverable'] ?? true;
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
                $newImage = $this->handleImages([$actor['icon']]);
                if ($user->avatar && $newImage !== $user->avatar) {
                    $this->bus->dispatch(new DeleteImageMessage($user->avatar->getId()));
                }
                $user->avatar = $newImage;
            }

            // Only update cover if image is set
            if (isset($actor['image'])) {
                $newImage = $this->handleImages([$actor['image']]);
                if ($user->cover && $newImage !== $user->cover) {
                    $this->bus->dispatch(new DeleteImageMessage($user->cover->getId()));
                }
                $user->cover = $newImage;
            }

            if (null !== $user->apFollowersUrl) {
                try {
                    $followersObj = $this->apHttpClient->getCollectionObject($user->apFollowersUrl);
                    if (isset($followersObj['totalItems']) and \is_int($followersObj['totalItems'])) {
                        $user->apFollowersCount = $followersObj['totalItems'];
                        $user->updateFollowCounts();
                    }
                } catch (InvalidApPostException $ignored) {
                }
            }

            // Write to DB
            $this->entityManager->flush();

            return $user;
        } else {
            $this->logger->debug("ActivityPubManager:updateUser:actorUrl: $actorUrl. Actor not found.");
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
                if ($tempFile = $this->imageManager->download($images[0]['url'])) {
                    $image = $this->imageRepository->findOrCreateFromPath($tempFile);
                    $image->sourceUrl = $images[0]['url'];
                    if ($image && isset($images[0]['name'])) {
                        $image->altText = $images[0]['name'];
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

    /**
     * Creates a new magazine (Group).
     *
     * @param string $actorUrl actor URL
     *
     * @return ?Magazine or null on error
     */
    private function createMagazine(string $actorUrl): ?Magazine
    {
        $this->magazineManager->create(
            $this->magazineFactory->createDtoFromAp($actorUrl, $this->buildHandle($actorUrl)),
            null,
            false
        );

        return $this->updateMagazine($actorUrl);
    }

    /**
     * Update an existing magazine.
     *
     * @param string $actorUrl actor URL
     *
     * @return ?Magazine or null on error
     */
    public function updateMagazine(string $actorUrl): ?Magazine
    {
        $this->logger->info('updating magazine "{magName}"', ['magName' => $actorUrl]);
        $magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);

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
                $newImage = $this->handleImages([$actor['icon']]);
                if ($magazine->icon && $newImage !== $magazine->icon) {
                    $this->bus->dispatch(new DeleteImageMessage($magazine->icon->getId()));
                }
                $magazine->icon = $newImage;
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
            $magazine->apAttributedToUrl = $actor['attributedTo'] ?? null;
            $magazine->apPreferredUsername = $actor['preferredUsername'] ?? null;
            $magazine->apDiscoverable = $actor['discoverable'] ?? true;
            $magazine->apPublicUrl = $actor['url'] ?? $actorUrl;
            $magazine->apDeletedAt = null;
            $magazine->apTimeoutAt = null;
            $magazine->apFetchedAt = new \DateTime();
            $magazine->isAdult = $actor['sensitive'] ?? false;

            if (null !== $magazine->apFollowersUrl) {
                try {
                    $this->logger->debug('updating remote followers of magazine "{magUrl}"', ['magUrl' => $actorUrl]);
                    $followersObj = $this->apHttpClient->getCollectionObject($magazine->apFollowersUrl);
                    if (isset($followersObj['totalItems']) and \is_int($followersObj['totalItems'])) {
                        $magazine->apFollowersCount = $followersObj['totalItems'];
                        $magazine->updateSubscriptionsCount();
                    }
                } catch (InvalidApPostException $ignored) {
                }
            }

            if (null !== $magazine->apAttributedToUrl) {
                try {
                    $this->logger->debug('fetching moderators of remote magazine "{magUrl}"', ['magUrl' => $actorUrl]);
                    $attributedObj = $this->apHttpClient->getCollectionObject($magazine->apAttributedToUrl);
                    $items = null;
                    if (isset($attributedObj['items']) and \is_array($attributedObj['items'])) {
                        $items = $attributedObj['items'];
                    } elseif (isset($attributedObj['orderedItems']) and \is_array($attributedObj['orderedItems'])) {
                        $items = $attributedObj['orderedItems'];
                    }

                    $this->logger->debug('got moderator items for magazine "{magName}": {json}', ['magName' => $magazine->name, 'json' => json_encode($attributedObj)]);

                    if (null !== $items) {
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
                                            $this->logger->info('adding "{user}" as moderator in "{magName}" because they are a mod upstream, but not locally', ['user' => $user->username, 'magName' => $magazine->name]);
                                            $this->magazineManager->addModerator(new ModeratorDto($magazine, $user, null));
                                        }
                                    }
                                } catch (\Exception) {
                                    $this->logger->warning('Something went wrong while fetching actor "{actor}" as moderator of "{magName}"', ['actor' => $item, 'magName' => $magazine->name]);
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
                            $this->logger->info('removing "{exMod}" from "{magName}" as mod locally because they are no longer mod upstream', ['exMod' => $modToRemove->username, 'magName' => $magazine->name]);
                            $this->magazineManager->removeModerator($modObject, null);
                        }
                    } else {
                        $this->logger->warning('could not update the moderators of "{url}", the response doesn\'t have a "items" or "orderedItems" property or it is not an array', ['url' => $actorUrl]);
                    }
                } catch (InvalidApPostException $ignored) {
                }
            }

            $this->entityManager->flush();

            return $magazine;
        } else {
            $this->logger->debug("ActivityPubManager:updateMagazine:actorUrl: $actorUrl. Actor not found.");
        }

        return null;
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
                    \is_array($activity['cc']) ? $activity['cc'] : [$activity['cc']],
                    \is_array($activity['to']) ? $activity['to'] : [$activity['to']]
                ), fn ($val) => !\in_array($val, [ActivityPubActivityInterface::PUBLIC_URL, $followersUrl, []])
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
    public function updateActor(string $actorUrl): null|Magazine|User
    {
        if ($this->userRepository->findOneBy(['apProfileId' => $actorUrl])) {
            return $this->updateUser($actorUrl);
        } elseif ($this->magazineRepository->findOneBy(['apProfileId' => $actorUrl])) {
            return $this->updateMagazine($actorUrl);
        }

        return null;
    }

    public function findOrCreateMagazineByToCCAndAudience(array $object): Magazine|null
    {
        $potentialGroups = self::getReceivers($object);
        $magazine = $this->magazineRepository->findByApGroupProfileId($potentialGroups);
        if ($magazine and $magazine->apId && (!$magazine->apFetchedAt || $magazine->apFetchedAt->modify('+1 Day') < (new \DateTime()))) {
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
        $res = [];
        if (isset($object['audience']) and \is_array($object['audience'])) {
            $res = array_merge($res, $object['audience']);
        } elseif (isset($object['audience']) and \is_string($object['audience'])) {
            $res[] = $object['audience'];
        }

        if (isset($object['to']) and \is_array($object['to'])) {
            $res = array_merge($res, $object['to']);
        } elseif (isset($object['to']) and \is_string($object['to'])) {
            $res[] = $object['to'];
        }

        if (isset($object['cc']) and \is_array($object['cc'])) {
            $res = array_merge($res, $object['cc']);
        } elseif (isset($object['cc']) and \is_string($object['cc'])) {
            $res[] = $object['cc'];
        }

        if (isset($object['object']) and \is_array($object['object'])) {
            if (isset($object['object']['audience']) and \is_array($object['object']['audience'])) {
                $res = array_merge($res, $object['object']['audience']);
            } elseif (isset($object['object']['audience']) and \is_string($object['object']['audience'])) {
                $res[] = $object['object']['audience'];
            }

            if (isset($object['object']['to']) and \is_array($object['object']['to'])) {
                $res = array_merge($res, $object['object']['to']);
            } elseif (isset($object['object']['to']) and \is_string($object['object']['to'])) {
                $res[] = $object['object']['to'];
            }

            if (isset($object['object']['cc']) and \is_array($object['object']['cc'])) {
                $res = array_merge($res, $object['object']['cc']);
            } elseif (isset($object['object']['cc']) and \is_string($object['object']['cc'])) {
                $res[] = $object['object']['cc'];
            }
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
    public function getEntityObject(string|array $apObject, array $fullPayload, callable $chainDispatch): null|Entry|EntryComment|Post|PostComment
    {
        $object = null;
        $calledUrl = null;
        if (\is_string($apObject)) {
            if (false === filter_var($apObject, FILTER_VALIDATE_URL)) {
                $this->logger->error('The like activity references an object by string, but that is not a URL, discarding the message', $fullPayload);

                return null;
            }
            $activity = $this->activityRepository->findByObjectId($apObject);
            $calledUrl = $apObject;
            if (!$activity) {
                $this->logger->debug('object is fetched from {url} because it is a string and could not be found in our repo', ['url' => $apObject]);
                $object = $this->apHttpClient->getActivityObject($apObject);
            }
        } else {
            $activity = $this->activityRepository->findByObjectId($apObject['id']);
            $calledUrl = $apObject['id'];
            if (!$activity) {
                $this->logger->debug('object is fetched from {url} because it is not a string and could not be found in our repo', ['url' => $apObject['id']]);
                $object = $apObject;
            }
        }

        if (!$activity && !$object) {
            $this->logger->error("The activity is still null and we couldn't get the object from the url, discarding", $fullPayload);

            return null;
        }

        if ($object) {
            $adjustedUrl = null;
            if ($object['id'] !== $calledUrl) {
                $this->logger->warning('the url {url} returned a different object id: {id}', ['url' => $calledUrl, 'id' => $object['id']]);
                $adjustedUrl = $object['id'];
            }

            $this->logger->debug('dispatching a ChainActivityMessage, because the object could not be found: {o}', ['o' => $apObject]);
            $this->logger->debug('the object for ChainActivityMessage with object {o}', ['o' => $object]);
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
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\ActivityPub\Server;
use App\DTO\ActivityPub\ImageDto;
use App\DTO\ActivityPub\VideoDto;
use App\DTO\ModeratorDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Image;
use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\MagazineFactory;
use App\Factory\UserFactory;
use App\Message\ActivityPub\UpdateActorMessage;
use App\Message\DeleteImageMessage;
use App\Repository\ImageRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Webfinger\WebFinger;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ActivityPubManager
{
    public function __construct(
        private readonly Server $server,
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
     * @param string $actorUrlOrHandle actor URL or actor handle
     *
     * @return User|Magazine|null or Magazine or null on error
     */
    public function findActorOrCreate(?string $actorUrlOrHandle): null|User|Magazine
    {
        if (\is_null($actorUrlOrHandle)) {
            return null;
        }

        $this->logger->debug('searching for actor at "{handle}"', ['handle' => $actorUrlOrHandle]);
        if (str_contains($actorUrlOrHandle, $this->settingsManager->get('KBIN_DOMAIN').'/m/')) {
            $magazine = str_replace('https://'.$this->settingsManager->get('KBIN_DOMAIN').'/m/', '', $actorUrlOrHandle);
            $this->logger->debug('found magazine "{magName}"', ['magName' => $magazine]);

            return $this->magazineRepository->findOneByName($magazine);
        }

        $actorUrl = $actorUrlOrHandle;
        if (false === filter_var($actorUrl, FILTER_VALIDATE_URL)) {
            if (!substr_count(ltrim($actorUrl, '@'), '@')) {
                return $this->userRepository->findOneBy(['username' => ltrim($actorUrl, '@')]);
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

        $actor = $this->apHttpClient->getActorObject($actorUrl);
        // Check if actor isn't empty (not set/null/empty array/etc.) and check if actor type is set
        if (!empty($actor) && isset($actor['type'])) {
            // User (we don't make a distinction between bots with type Service as Lemmy does)
            if (\in_array($actor['type'], User::USER_TYPES)) {
                $user = $this->userRepository->findOneBy(['apProfileId' => $actorUrl]);
                $this->logger->debug('found remote user at "{url}"', ['url' => $actorUrl]);
                if (!$user) {
                    $user = $this->createUser($actorUrl);
                } else {
                    if (!$user->apFetchedAt || $user->apFetchedAt->modify('+1 hour') < (new \DateTime())) {
                        try {
                            $this->bus->dispatch(new UpdateActorMessage($user->apProfileId));
                        } catch (\Exception $e) {
                        }
                    }
                }

                return $user;
            }

            // Magazine (Group)
            if ('Group' === $actor['type']) {
                // User
                $magazine = $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);
                $this->logger->debug('found magazine at "{url}"', ['url' => $actorUrl]);
                if (!$magazine) {
                    $magazine = $this->createMagazine($actorUrl);
                } else {
                    if (!$magazine->apFetchedAt || $magazine->apFetchedAt->modify('+1 hour') < (new \DateTime())) {
                        try {
                            $this->bus->dispatch(new UpdateActorMessage($magazine->apProfileId));
                        } catch (\Exception $e) {
                        }
                    }
                }

                return $magazine;
            }
        }

        return null;
    }

    /**
     * @throws \LogicException when the returned actor is not a user or is null
     */
    public function findUserActorOrCreateOrThrow(string $actorUrlOrHandle): User
    {
        $object = $this->findActorOrCreate($actorUrlOrHandle);
        if (!$object) {
            throw new \LogicException("could not find actor for 'object' property at: '$actorUrlOrHandle'");
        } elseif (!$object instanceof User) {
            throw new \LogicException("could not find user actor for 'object' property at: '$actorUrlOrHandle'");
        }

        return $object;
    }

    public function webfinger(string $id): WebFinger
    {
        $this->logger->debug('fetching webfinger "{id}"', ['id' => $id]);
        $this->webFingerFactory::setServer($this->server->create());

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

            // Only update about when summary is set
            if (isset($actor['summary'])) {
                $converter = new HtmlConverter(['strip_tags' => true]);
                $user->about = stripslashes($converter->convert($actor['summary']));
            }

            // Only update avatar if icon is set
            if (isset($actor['icon'])) {
                $newImage = $this->handleImages([$actor['icon']]);
                if ($user->avatar && $newImage !== $user->avatar) {
                    $this->bus->dispatch(new DeleteImageMessage($user->avatar->filePath));
                }
                $user->avatar = $newImage;
            }

            // Only update cover if image is set
            if (isset($actor['image'])) {
                $newImage = $this->handleImages([$actor['image']]);
                if ($user->cover && $newImage !== $user->cover) {
                    $this->bus->dispatch(new DeleteImageMessage($user->cover->filePath));
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
            return null;
        }
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
                    if ($image && isset($images[0]['name'])) {
                        $image->altText = $images[0]['name'];
                    }
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
            $this->userRepository->findAdmin(),
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
        if (isset($actor['endpoints']['sharedInbox']) || isset($actor['inbox'])) {
            if (isset($actor['summary'])) {
                $converter = new HtmlConverter(['strip_tags' => true]);
                $magazine->description = stripslashes($converter->convert($actor['summary']));
            }

            if (isset($actor['icon'])) {
                $newImage = $this->handleImages([$actor['icon']]);
                if ($magazine->icon && $newImage !== $magazine->icon) {
                    $this->bus->dispatch(new DeleteImageMessage($magazine->icon->filePath));
                }
                $magazine->icon = $newImage;
            }

            if ($actor['name']) {
                $magazine->title = $actor['name'];
            } elseif ($actor['preferredUsername']) {
                $magazine->title = $actor['preferredUsername'];
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
            $magazine->isAdult = (bool) $actor['sensitive'];

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
                        foreach ($magazine->moderators as /* @var $mod Moderator */ $mod) {
                            if (!$mod->isOwner) {
                                $moderatorsToRemove[] = $mod->user;
                            }
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
            return null;
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
        $this->logger->info('updating actor at {url}', ['url' => $actorUrl]);
        $actor = $this->apHttpClient->getActorObject($actorUrl);

        // User (We don't make a distinction between bots with type Service as Lemmy does)
        if (isset($actor['type']) && \in_array($actor['type'], User::USER_TYPES)) {
            return $this->updateUser($actorUrl);
        }

        return $this->updateMagazine($actorUrl);
    }

    public function findOrCreateMagazineByToAndCC(array $object): Magazine|null
    {
        $potentialGroups = self::getReceivers($object);
        $magazine = $this->magazineRepository->findByApGroupProfileId($potentialGroups);
        if ($magazine and $magazine->apId and $magazine->apFetchedAt->modify('+1 Day') < (new \DateTime())) {
            $this->bus->dispatch(new UpdateActorMessage($magazine->apPublicUrl));
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
        if (isset($object['to']) and \is_array($object['to'])) {
            $res = $object['to'];
        } elseif (isset($object['to']) and \is_string($object['to'])) {
            $res[] = $object['to'];
        }

        if (isset($object['cc']) and \is_array($object['cc'])) {
            $res = array_merge($res, $object['cc']);
        } elseif (isset($object['cc']) and \is_string($object['cc'])) {
            $res[] = $object['cc'];
        }

        if (isset($object['object']) and \is_array($object['object'])) {
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
}

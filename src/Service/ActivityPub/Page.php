<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\DTO\EntryDto;
use App\Entity\Entry;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\InstanceBannedException;
use App\Exception\PostingRestrictedException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Exception\UserDeletedException;
use App\Factory\ImageFactory;
use App\Repository\ApActivityRepository;
use App\Repository\InstanceRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Page extends ActivityPubContent
{
    public function __construct(
        private readonly ApActivityRepository $repository,
        private readonly EntryManager $entryManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly ImageFactory $imageFactory,
        private readonly ApObjectExtractor $objectExtractor,
        private readonly LoggerInterface $logger,
        private readonly InstanceRepository $instanceRepository,
    ) {
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws EntityNotFoundException    if the user could not be found or a sub exception occurred
     * @throws PostingRestrictedException if the target magazine has Magazine::postingRestrictedToMods = true and the actor is a magazine or a user that is not a mod
     * @throws InstanceBannedException    if the actor is from a banned instance
     * @throws \Exception                 if there was an error
     */
    public function create(array $object, bool $stickyIt = false): Entry
    {
        // First try to find the activity object in the database
        $current = $this->repository->findByObjectId($object['id']);
        if ($current) {
            return $this->entityManager->getRepository($current['type'])->find((int) $current['id']);
        }
        $actorUrl = $this->activityPubManager->getSingleActorFromAttributedTo($object['attributedTo']);
        if ($this->settingsManager->isBannedInstance($actorUrl)) {
            throw new InstanceBannedException();
        }
        $actor = $this->activityPubManager->findActorOrCreate($actorUrl);
        if (!empty($actor)) {
            if ($actor->isBanned) {
                throw new UserBannedException();
            }
            if ($actor->isDeleted || $actor->isTrashed() || $actor->isSoftDeleted()) {
                throw new UserDeletedException();
            }

            $current = $this->repository->findByObjectId($object['id']);
            if ($current) {
                $this->logger->debug('Page already exists, not creating it');

                return $this->entityManager->getRepository($current['type'])->find((int) $current['id']);
            }

            if (\is_string($object['to'])) {
                $object['to'] = [$object['to']];
            }

            if (\is_string($object['cc'])) {
                $object['cc'] = [$object['cc']];
            }

            $magazine = $this->activityPubManager->findOrCreateMagazineByToCCAndAudience($object);
            if ($magazine->isActorPostingRestricted($actor)) {
                throw new PostingRestrictedException($magazine, $actor);
            }

            $dto = new EntryDto();
            $dto->magazine = $magazine;
            $dto->title = $object['name'];
            $dto->apId = $object['id'];

            if ((isset($object['attachment']) || isset($object['image'])) && $image = $this->activityPubManager->handleImages($object['attachment'])) {
                $this->logger->debug("adding image to entry '{title}', {image}", ['title' => $dto->title, 'image' => $image->getId()]);
                $dto->image = $this->imageFactory->createDto($image);
            }

            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            $dto->visibility = $this->getVisibility($object, $actor);
            $this->extractUrlIntoDto($dto, $object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
            }

            if (isset($object['sensitive']) && true === $object['sensitive']) {
                $dto->isAdult = true;
            }

            if (!empty($object['language'])) {
                $dto->lang = $object['language']['identifier'];
            } elseif (!empty($object['contentMap'])) {
                $dto->lang = array_keys($object['contentMap'])[0];
            } else {
                $dto->lang = $this->settingsManager->get('KBIN_DEFAULT_LANG');
            }
            $dto->apLikeCount = $this->activityPubManager->extractRemoteLikeCount($object);
            $dto->apDislikeCount = $this->activityPubManager->extractRemoteDislikeCount($object);
            $dto->apShareCount = $this->activityPubManager->extractRemoteShareCount($object);

            $this->logger->debug('creating page');

            return $this->entryManager->create($dto, $actor, false, $stickyIt);
        } else {
            throw new EntityNotFoundException('Actor could not be found for entry.');
        }
    }

    private function extractUrlIntoDto(EntryDto $dto, ?array $object, User $actor): void
    {
        $attachment = \array_key_exists('attachment', $object) ? $object['attachment'] : null;

        $dto->url = ActivityPubManager::extractUrlFromAttachment($attachment);
        if (null === $dto->url) {
            $instance = $this->instanceRepository->findOneBy(['domain' => $actor->apDomain]);
            if ($instance && 'peertube' === $instance->software) {
                // we make an exception for PeerTube as we need their embed viewer.
                // Normally the URL field only links to a user-friendly UI if that differs from the AP id,
                // which we do not want to have as a URL, but without the embed from PeerTube
                // a video is only viewable by clicking more -> open original URL
                // which is not very user-friendly.
                $url = \array_key_exists('url', $object) ? $object['url'] : null;
                $dto->url = ActivityPubManager::extractUrlFromAttachment($url);
            }
        }
    }
}

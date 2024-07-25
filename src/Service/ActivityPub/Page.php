<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\User;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Exception\UserDeletedException;
use App\Factory\ImageFactory;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Page
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
    ) {
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws \Exception           if the user could not be found or a sub exception occurred
     */
    public function create(array $object, bool $stickyIt = false): Entry
    {
        $actorUrl = $this->activityPubManager->getActorFromAttributedTo($object['attributedTo']);
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
            $this->handleUrl($dto, $object);
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
            throw new \Exception('Actor could not be found for entry.');
        }
    }

    /**
     * @throws \Exception
     */
    private function getVisibility(array $object, User $actor): string
    {
        if (!\in_array(
            ActivityPubActivityInterface::PUBLIC_URL,
            array_merge($object['to'] ?? [], $object['cc'] ?? [])
        )) {
            if (
                !\in_array(
                    $actor->apFollowersUrl,
                    array_merge($object['to'] ?? [], $object['cc'] ?? [])
                )
            ) {
                throw new \Exception('PM: not implemented.');
            }

            return VisibilityInterface::VISIBILITY_PRIVATE;
        }

        return VisibilityInterface::VISIBILITY_VISIBLE;
    }

    private function handleUrl(EntryDto $dto, ?array $object): void
    {
        $attachment = \array_key_exists('attachment', $object) ? $object['attachment'] : null;

        try {
            if (\is_array($attachment)) {
                $link = array_filter(
                    $attachment,
                    fn ($val) => \in_array($val['type'], ['Link'])
                );

                if (\is_array($link) && !empty($link[0]) && isset($link[0]['href'])) {
                    $dto->url = $link[0]['href'];
                } elseif (\is_array($link) && isset($link['href'])) {
                    $dto->url = $link['href'];
                }
            }
        } catch (\Exception $e) {
        }

        if (!$dto->url && isset($object['url'])) {
            $dto->url = $this->activityPubManager->extractUrl($object['url']);
        }
    }

    /**
     * @throws \Exception
     */
    private function handleDate(EntryDto $dto, string $date): void
    {
        $dto->createdAt = new \DateTimeImmutable($date);
        $dto->lastActive = new \DateTime($date);
    }

    private function handleSensitiveMedia(PostDto|PostCommentDto|EntryCommentDto|EntryDto $dto, string|bool $sensitive): void
    {
        if (true === filter_var($sensitive, FILTER_VALIDATE_BOOLEAN)) {
            $dto->isAdult = true;
        }
    }
}

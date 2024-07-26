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
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Exception\UserDeletedException;
use App\Factory\ImageFactory;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryCommentManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Note
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ApActivityRepository $repository,
        private readonly PostManager $postManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostCommentManager $postCommentManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly ImageFactory $imageFactory,
        private readonly ApObjectExtractor $objectExtractor,
    ) {
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws \Exception
     */
    public function create(array $object, ?array $root = null, bool $stickyIt = false): EntryComment|PostComment|Post
    {
        $current = $this->repository->findByObjectId($object['id']);
        if ($current) {
            return $this->entityManager->getRepository($current['type'])->find((int) $current['id']);
        }

        if (\is_string($object['to'])) {
            $object['to'] = [$object['to']];
        }

        if (\is_string($object['cc'])) {
            $object['cc'] = [$object['cc']];
        }

        if (isset($object['inReplyTo']) && $replyTo = $object['inReplyTo']) {
            // Create post or entry comment
            $parentObjectId = $this->repository->findByObjectId($replyTo);
            $parent = $this->entityManager->getRepository($parentObjectId['type'])->find((int) $parentObjectId['id']);

            if (Entry::class === \get_class($parent)) {
                $root = $parent;

                return $this->createEntryComment($object, $parent, $root);
            }

            if (EntryComment::class === \get_class($parent)) {
                $root = $parent->entry;

                return $this->createEntryComment($object, $parent, $root);
            }

            if (Post::class === \get_class($parent)) {
                $root = $parent;

                return $this->createPostComment($object, $parent, $root);
            }

            if (PostComment::class === \get_class($parent)) {
                $root = $parent->post;

                return $this->createPostComment($object, $parent, $root);
            }
        }

        return $this->createPost($object, $stickyIt);
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws \Exception
     */
    private function createEntryComment(array $object, ActivityPubActivityInterface $parent, ?ActivityPubActivityInterface $root = null): EntryComment
    {
        $dto = new EntryCommentDto();
        if ($parent instanceof EntryComment) {
            $dto->parent = $parent;
            $dto->root = $parent->root ?? $parent;
        }

        $dto->entry = $root;
        $dto->apId = $object['id'];

        if (
            isset($object['attachment'])
            && $image = $this->activityPubManager->handleImages($object['attachment'])
        ) {
            $dto->image = $this->imageFactory->createDto($image);
        }

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
            if ($actor->isBanned) {
                throw new UserBannedException();
            }
            if ($actor->isDeleted || $actor->isSoftDeleted() || $actor->isTrashed()) {
                throw new UserDeletedException();
            }
            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            if ($media = $this->objectExtractor->getExternalMediaBody($object)) {
                $dto->body .= $media;
            }

            $dto->visibility = $this->getVisibility($object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
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

            return $this->entryCommentManager->create($dto, $actor, false);
        } else {
            throw new \Exception('Actor could not be found for entry comment.');
        }
    }

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

    private function handleDate(PostDto|PostCommentDto|EntryCommentDto|EntryDto $dto, string $date): void
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

    /**
     * @throws UserDeletedException
     * @throws TagBannedException
     * @throws UserBannedException
     */
    private function createPost(array $object, bool $stickyIt = false): Post
    {
        $dto = new PostDto();
        $dto->magazine = $this->activityPubManager->findOrCreateMagazineByToCCAndAudience($object);
        $dto->apId = $object['id'];

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
            if ($actor->isBanned) {
                throw new UserBannedException();
            }
            if ($actor->isDeleted || $actor->isSoftDeleted() || $actor->isTrashed()) {
                throw new UserDeletedException();
            }

            if (isset($object['attachment']) && $image = $this->activityPubManager->handleImages($object['attachment'])) {
                $dto->image = $this->imageFactory->createDto($image);
                $this->logger->debug("adding image to post '{title}', {image}", ['title' => $dto->slug, 'image' => $image->getId()]);
            }

            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            if ($media = $this->objectExtractor->getExternalMediaBody($object)) {
                $dto->body .= $media;
            }

            $dto->visibility = $this->getVisibility($object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
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

            return $this->postManager->create($dto, $actor, false, $stickyIt);
        } else {
            throw new \Exception('Actor could not be found for post.');
        }
    }

    /**
     * @throws UserDeletedException
     * @throws TagBannedException
     * @throws UserBannedException
     */
    private function createPostComment(array $object, ActivityPubActivityInterface $parent, ?ActivityPubActivityInterface $root = null): PostComment
    {
        $dto = new PostCommentDto();
        if ($parent instanceof PostComment) {
            $dto->parent = $parent;
        }

        $dto->post = $root;
        $dto->apId = $object['id'];

        if (
            isset($object['attachment'])
            && $image = $this->activityPubManager->handleImages($object['attachment'])
        ) {
            $dto->image = $this->imageFactory->createDto($image);
        }

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
            if ($actor->isBanned) {
                throw new UserBannedException();
            }
            if ($actor->isDeleted || $actor->isSoftDeleted() || $actor->isTrashed()) {
                throw new UserDeletedException();
            }
            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            if ($media = $this->objectExtractor->getExternalMediaBody($object)) {
                $dto->body .= $media;
            }

            $dto->visibility = $this->getVisibility($object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
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

            return $this->postCommentManager->create($dto, $actor, false);
        } else {
            throw new \Exception('Actor could not be found for post comment.');
        }
    }
}

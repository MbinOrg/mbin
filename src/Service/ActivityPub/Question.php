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
use App\Factory\ImageFactory;
use App\Repository\ApActivityRepository;
use App\Repository\MagazineRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryCommentManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;

class Question
{
    public function __construct(
        private readonly ApActivityRepository $repository,
        private readonly PostManager $postManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostCommentManager $postCommentManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MarkdownConverter $markdownConverter,
        private readonly SettingsManager $settingsManager,
        private readonly ImageFactory $imageFactory,
        private readonly ApObjectExtractor $objectExtractor,
    ) {
    }

    public function create(array $object, ?array $root = null): ActivityPubActivityInterface
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
            $parent = $this->repository->findByObjectId($replyTo);
            $parent = $this->entityManager->getRepository($parent['type'])->find((int) $parent['id']);

            $root = null;
            $fn = null;

            if (Entry::class === \get_class($parent)) {
                $root = $parent;
                $fn = 'createEntryComment';
            }

            if (EntryComment::class === \get_class($parent)) {
                $root = $parent->entry;
                $fn = 'createEntryComment';
            }

            if (Post::class === \get_class($parent)) {
                $root = $parent;
                $fn = 'createPostComment';
            }

            if (PostComment::class === \get_class($parent)) {
                $root = $parent->post;
                $fn = 'createPostComment';
            }

            return $this->$fn($object, $parent, $root);
        }

        return $this->createPost($object);
    }

    private function createEntryComment(
        array $object,
        ActivityPubActivityInterface $parent,
        ?ActivityPubActivityInterface $root = null,
    ): ActivityPubActivityInterface {
        $dto = new EntryCommentDto();
        if ($parent instanceof EntryComment) {
            $dto->parent = $parent;
            $dto->root = $parent->root ?? $parent;
        }

        $dto->entry = $root;
        $dto->apId = $object['id'];

        if (isset($object['attachment'])) {
            if ($image = $this->activityPubManager->handleImages($object['attachment'])) {
                $dto->image = $this->imageFactory->createDto($image);
            }
        }

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
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

            return $this->entryCommentManager->create(
                $dto,
                $actor,
                false
            );
        } else {
            throw new \Exception('Actor could not be found for comment.');
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

    private function createPost(
        array $object,
    ): ActivityPubActivityInterface {
        $dto = new PostDto();
        $dto->magazine = $this->magazineRepository->findOneByName('random'); // @todo magazine by tags
        $dto->apId = $object['id'];

        if (isset($object['attachment'])) {
            if ($image = $this->activityPubManager->handleImages($object['attachment'])) {
                $dto->image = $this->imageFactory->createDto($image);
            }
        }

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            if ($media = $this->objectExtractor->getExternalMediaBody($object)) {
                $dto->body .= $media;
            }

            $dto->visibility = $this->getVisibility($object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
            }

            return $this->postManager->create(
                $dto,
                $actor,
                false
            );
        } else {
            throw new \Exception('Actor could not be found for post.');
        }
    }

    private function createPostComment(
        array $object,
        ActivityPubActivityInterface $parent,
        ?ActivityPubActivityInterface $root = null,
    ): ActivityPubActivityInterface {
        $dto = new PostCommentDto();
        if ($parent instanceof PostComment) {
            $dto->parent = $parent;
        }

        $dto->post = $root;
        $dto->apId = $object['id'];

        if (isset($object['attachment'])) {
            if ($image = $this->activityPubManager->handleImages($object['attachment'])) {
                $dto->image = $this->imageFactory->createDto($image);
            }
        }

        $actor = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        if (!empty($actor)) {
            $dto->body = $this->objectExtractor->getMarkdownBody($object);
            if ($media = $this->objectExtractor->getExternalMediaBody($object)) {
                $dto->body .= $media;
            }

            $dto->visibility = $this->getVisibility($object, $actor);
            $this->handleDate($dto, $object['published']);
            if (isset($object['sensitive'])) {
                $this->handleSensitiveMedia($dto, $object['sensitive']);
            }

            return $this->postCommentManager->create(
                $dto,
                $actor,
                false
            );
        } else {
            throw new \Exception('Actor could not be found post comment.');
        }
    }
}

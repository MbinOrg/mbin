<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PostCommentDto;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\PostComment;
use App\Entity\User;
use App\Event\PostComment\PostCommentBeforeDeletedEvent;
use App\Event\PostComment\PostCommentBeforePurgeEvent;
use App\Event\PostComment\PostCommentCreatedEvent;
use App\Event\PostComment\PostCommentDeletedEvent;
use App\Event\PostComment\PostCommentEditedEvent;
use App\Event\PostComment\PostCommentPurgedEvent;
use App\Event\PostComment\PostCommentRestoredEvent;
use App\Exception\InstanceBannedException;
use App\Exception\PostLockedException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Factory\PostCommentFactory;
use App\Message\DeleteImageMessage;
use App\Repository\ImageRepository;
use App\Service\Contracts\ContentManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Webmozart\Assert\Assert;

class PostCommentManager implements ContentManagerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TagManager $tagManager,
        private readonly TagExtractor $tagExtractor,
        private readonly MentionManager $mentionManager,
        private readonly PostCommentFactory $factory,
        private readonly ImageRepository $imageRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly RateLimiterFactoryInterface $postCommentLimiter,
        private readonly MessageBusInterface $bus,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws InstanceBannedException
     * @throws TooManyRequestsHttpException
     * @throws PostLockedException
     * @throws \Exception
     */
    public function create(PostCommentDto $dto, User $user, $rateLimit = true): PostComment
    {
        if ($rateLimit) {
            $limiter = $this->postCommentLimiter->create($dto->ip);
            if ($limiter && false === $limiter->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        if ($dto->post->magazine->isBanned($user) || $user->isBanned()) {
            throw new UserBannedException();
        }

        if ($this->tagManager->isAnyTagBanned($this->tagManager->extract($dto->body))) {
            throw new TagBannedException();
        }

        if (null !== $dto->post->magazine->apId && $this->settingsManager->isBannedInstance($dto->post->magazine->apInboxUrl)) {
            throw new InstanceBannedException();
        }

        if ($dto->post->isLocked) {
            throw new PostLockedException();
        }

        $comment = $this->factory->createFromDto($dto, $user);

        $comment->magazine = $dto->post->magazine;
        $comment->lang = $dto->lang;
        $comment->isAdult = $dto->isAdult || $comment->magazine->isAdult;
        $comment->image = $dto->image ? $this->imageRepository->find($dto->image->id) : null;
        if ($comment->image && !$comment->image->altText) {
            $comment->image->altText = $dto->imageAlt;
        }
        $comment->mentions = $dto->body
            ? array_merge($dto->mentions ?? [], $this->mentionManager->handleChain($comment))
            : $dto->mentions;
        $comment->visibility = $dto->visibility;
        $comment->apId = $dto->apId;
        $comment->apLikeCount = $dto->apLikeCount;
        $comment->apDislikeCount = $dto->apDislikeCount;
        $comment->apShareCount = $dto->apShareCount;
        $comment->magazine->lastActive = new \DateTime();
        $comment->user->lastActive = new \DateTime();
        $comment->lastActive = $dto->lastActive ?? $comment->lastActive;
        $comment->createdAt = $dto->createdAt ?? $comment->createdAt;
        if (empty($comment->body) && null === $comment->image) {
            throw new \Exception('Comment body and image cannot be empty');
        }

        $comment->post->addComment($comment);
        $comment->updateScore();
        $comment->updateRanking();

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->tagManager->updatePostCommentTags($comment, $this->tagExtractor->extract($comment->body) ?? []);

        $this->dispatcher->dispatch(new PostCommentCreatedEvent($comment));

        return $comment;
    }

    public function canUserEditPostComment(PostComment $postComment, User $user): bool
    {
        $postCommentHost = null !== $postComment->apId ? parse_url($postComment->apId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');
        $userHost = null !== $user->apId ? parse_url($user->apProfileId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');
        $magazineHost = null !== $postComment->magazine->apId ? parse_url($postComment->magazine->apProfileId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');

        return $postCommentHost === $userHost || $userHost === $magazineHost || $postComment->magazine->userIsModerator($user);
    }

    /**
     * @throws \Exception
     */
    public function edit(PostComment $comment, PostCommentDto $dto, ?User $editedBy = null): PostComment
    {
        Assert::same($comment->post->getId(), $dto->post->getId());

        $comment->body = $dto->body;
        $comment->lang = $dto->lang;
        $comment->isAdult = $dto->isAdult || $comment->magazine->isAdult;
        $oldImage = $comment->image;
        if ($dto->image) {
            $comment->image = $this->imageRepository->find($dto->image->id);
        }
        $this->tagManager->updatePostCommentTags($comment, $this->tagExtractor->extract($dto->body) ?? []);
        $comment->mentions = $dto->body
            ? array_merge($dto->mentions ?? [], $this->mentionManager->handleChain($comment))
            : $dto->mentions;
        $comment->visibility = $dto->visibility;
        $comment->editedAt = new \DateTimeImmutable('@'.time());
        if (empty($comment->body) && null === $comment->image) {
            throw new \Exception('Comment body and image cannot be empty');
        }

        $comment->apLikeCount = $dto->apLikeCount;
        $comment->apDislikeCount = $dto->apDislikeCount;
        $comment->apShareCount = $dto->apShareCount;
        $comment->updateScore();
        $comment->updateRanking();

        $this->entityManager->flush();

        if ($oldImage && $comment->image !== $oldImage) {
            $this->bus->dispatch(new DeleteImageMessage($oldImage->getId()));
        }

        $this->dispatcher->dispatch(new PostCommentEditedEvent($comment, $editedBy));

        return $comment;
    }

    public function delete(User $user, PostComment $comment): void
    {
        if ($user->apDomain && $user->apDomain !== parse_url($comment->apId ?? '', PHP_URL_HOST) && !$comment->magazine->userIsModerator($user)) {
            $this->logger->info('Got a delete activity from user {u}, but they are not from the same instance as the deleted post and they are not a moderator on {m]', ['u' => $user->apId, 'm' => $comment->magazine->apId ?? $comment->magazine->name]);

            return;
        }

        if ($comment->isAuthor($user) && $comment->children->isEmpty()) {
            $this->purge($user, $comment);

            return;
        }

        $this->isTrashed($user, $comment) ? $comment->trash() : $comment->softDelete();

        $this->dispatcher->dispatch(new PostCommentBeforeDeletedEvent($comment, $user));

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new PostCommentDeletedEvent($comment, $user));
    }

    public function trash(User $user, PostComment $comment): void
    {
        $comment->trash();

        $this->dispatcher->dispatch(new PostCommentBeforeDeletedEvent($comment, $user));

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new PostCommentDeletedEvent($comment, $user));
    }

    public function purge(User $user, PostComment $comment): void
    {
        $this->dispatcher->dispatch(new PostCommentBeforePurgeEvent($comment, $user));

        $magazine = $comment->post->magazine;
        $image = $comment->image?->getId();
        $comment->post->removeComment($comment);
        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new PostCommentPurgedEvent($magazine));

        if ($image) {
            $this->bus->dispatch(new DeleteImageMessage($image));
        }
    }

    private function isTrashed(User $user, PostComment $comment): bool
    {
        return !$comment->isAuthor($user);
    }

    /**
     * @throws \Exception
     */
    public function restore(User $user, PostComment $comment): void
    {
        if (VisibilityInterface::VISIBILITY_TRASHED !== $comment->visibility) {
            throw new \Exception('Invalid visibility');
        }

        $comment->visibility = VisibilityInterface::VISIBILITY_VISIBLE;

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new PostCommentRestoredEvent($comment, $user));
    }

    public function createDto(PostComment $comment): PostCommentDto
    {
        return $this->factory->createDto($comment);
    }

    public function detachImage(PostComment $comment): void
    {
        $image = $comment->image->getId();

        $comment->image = null;

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->bus->dispatch(new DeleteImageMessage($image));
    }
}

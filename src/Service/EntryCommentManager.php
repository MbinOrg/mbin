<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EntryCommentDto;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\EntryComment;
use App\Entity\User;
use App\Event\EntryComment\EntryCommentBeforeDeletedEvent;
use App\Event\EntryComment\EntryCommentBeforePurgeEvent;
use App\Event\EntryComment\EntryCommentCreatedEvent;
use App\Event\EntryComment\EntryCommentDeletedEvent;
use App\Event\EntryComment\EntryCommentEditedEvent;
use App\Event\EntryComment\EntryCommentPurgedEvent;
use App\Event\EntryComment\EntryCommentRestoredEvent;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Factory\EntryCommentFactory;
use App\Message\DeleteImageMessage;
use App\Repository\ImageRepository;
use App\Service\Contracts\ContentManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Webmozart\Assert\Assert;

class EntryCommentManager implements ContentManagerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TagManager $tagManager,
        private readonly TagExtractor $tagExtractor,
        private readonly MentionManager $mentionManager,
        private readonly EntryCommentFactory $factory,
        private readonly RateLimiterFactory $entryCommentLimiter,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageRepository $imageRepository,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function create(EntryCommentDto $dto, User $user, $rateLimit = true): EntryComment
    {
        if (!$user->apId) {
            $user->ip = $dto->ip;
        }
        
        if ($rateLimit) {
            $limiter = $this->entryCommentLimiter->create($dto->ip);
            if ($limiter && false === $limiter->consume()->isAccepted()) {
                throw new TooManyRequestsHttpException();
            }
        }

        if ($dto->entry->magazine->isBanned($user) || $user->isBanned()) {
            throw new UserBannedException();
        }

        if ($this->tagManager->isAnyTagBanned($this->tagManager->extract($dto->body))) {
            throw new TagBannedException();
        }

        $comment = $this->factory->createFromDto($dto, $user);

        $comment->magazine = $dto->entry->magazine;
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

        $comment->entry->addComment($comment);

        $comment->updateScore();
        $comment->updateRanking();

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->tagManager->updateEntryCommentTags($comment, $this->tagExtractor->extract($comment->body) ?? []);

        $this->dispatcher->dispatch(new EntryCommentCreatedEvent($comment));

        return $comment;
    }

    public function canUserEditComment(EntryComment $comment, User $user): bool
    {
        $entryCommentHost = null !== $comment->apId ? parse_url($comment->apId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');
        $userHost = null !== $user->apId ? parse_url($user->apProfileId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');
        $magazineHost = null !== $comment->magazine->apId ? parse_url($comment->magazine->apProfileId, PHP_URL_HOST) : $this->settingsManager->get('KBIN_DOMAIN');

        return $entryCommentHost === $userHost || $userHost === $magazineHost || $comment->magazine->userIsModerator($user);
    }

    public function edit(EntryComment $comment, EntryCommentDto $dto, ?User $editedByUser = null): EntryComment
    {
        Assert::same($comment->entry->getId(), $dto->entry->getId());

        $comment->body = $dto->body;
        $comment->lang = $dto->lang;
        $comment->isAdult = $dto->isAdult || $comment->magazine->isAdult;
        $oldImage = $comment->image;
        if ($dto->image) {
            $comment->image = $this->imageRepository->find($dto->image->id);
        }
        $this->tagManager->updateEntryCommentTags($comment, $this->tagManager->getTagsFromEntryCommentDto($dto));
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

        $this->dispatcher->dispatch(new EntryCommentEditedEvent($comment, $editedByUser));

        return $comment;
    }

    public function delete(User $user, EntryComment $comment): void
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

        $this->dispatcher->dispatch(new EntryCommentBeforeDeletedEvent($comment, $user));

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new EntryCommentDeletedEvent($comment, $user));
    }

    public function trash(User $user, EntryComment $comment): void
    {
        $comment->trash();

        $this->dispatcher->dispatch(new EntryCommentBeforeDeletedEvent($comment, $user));

        $this->entityManager->flush();

        $this->dispatcher->dispatch(new EntryCommentDeletedEvent($comment, $user));
    }

    public function purge(User $user, EntryComment $comment): void
    {
        $this->dispatcher->dispatch(new EntryCommentBeforePurgeEvent($comment, $user));

        $magazine = $comment->entry->magazine;
        $image = $comment->image?->getId();
        $comment->entry->removeComment($comment);

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        if ($image) {
            $this->bus->dispatch(new DeleteImageMessage($image));
        }

        $this->dispatcher->dispatch(new EntryCommentPurgedEvent($magazine));
    }

    private function isTrashed(User $user, EntryComment $comment): bool
    {
        return !$comment->isAuthor($user);
    }

    public function restore(User $user, EntryComment $comment): void
    {
        if (VisibilityInterface::VISIBILITY_TRASHED !== $comment->visibility) {
            throw new \Exception('Invalid visibility');
        }

        $comment->visibility = VisibilityInterface::VISIBILITY_VISIBLE;

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new EntryCommentRestoredEvent($comment, $user));
    }

    public function createDto(EntryComment $comment): EntryCommentDto
    {
        return $this->factory->createDto($comment);
    }

    public function detachImage(EntryComment $comment): void
    {
        $image = $comment->image->getId();

        $comment->image = null;

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->bus->dispatch(new DeleteImageMessage($image));
    }
}

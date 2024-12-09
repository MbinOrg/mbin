<?php

declare(strict_types=1);

namespace App\EventSubscriber\PostComment;

use App\Entity\PostComment;
use App\Entity\User;
use App\Event\PostComment\PostCommentBeforeDeletedEvent;
use App\Event\PostComment\PostCommentBeforePurgeEvent;
use App\Event\PostComment\PostCommentDeletedEvent;
use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Message\Notification\PostCommentDeletedNotificationMessage;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

class PostCommentDeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $bus,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostCommentDeletedEvent::class => 'onPostCommentDeleted',
            PostCommentBeforePurgeEvent::class => 'onPostCommentBeforePurge',
            PostCommentBeforeDeletedEvent::class => 'onPostBeforeDelete',
        ];
    }

    public function onPostCommentDeleted(PostCommentDeletedEvent $event): void
    {
        $this->cache->invalidateTags([
            'post_'.$event->comment->post->getId(),
            'post_comment_'.$event->comment->root?->getId() ?? $event->comment->getId(),
        ]);

        $this->bus->dispatch(new PostCommentDeletedNotificationMessage($event->comment->getId()));
    }

    public function onPostBeforeDelete(PostCommentBeforeDeletedEvent $event): void
    {
        $this->onPostCommentBeforeDeleteImpl($event->user, $event->comment);
    }

    public function onPostCommentBeforePurge(PostCommentBeforePurgeEvent $event): void
    {
        $this->onPostCommentBeforeDeleteImpl($event->user, $event->comment);
    }

    public function onPostCommentBeforeDeleteImpl(?User $user, PostComment $comment): void
    {
        $this->cache->invalidateTags([
            'post_'.$comment->post->getId(),
            'post_comment_'.$comment->root?->getId() ?? $comment->getId(),
        ]);

        $this->bus->dispatch(new PostCommentDeletedNotificationMessage($comment->getId()));

        if (!$comment->apId || !$comment->magazine->apId || (null !== $user && $comment->magazine->userIsModerator($user))) {
            $activity = $this->deleteWrapper->adjustDeletePayload($user, $comment);
            $payload = $this->activityJsonBuilder->buildActivityJson($activity);
            $this->bus->dispatch(new DeleteMessage($payload, $comment->user->getId(), $comment->magazine->getId()));
        }
    }
}

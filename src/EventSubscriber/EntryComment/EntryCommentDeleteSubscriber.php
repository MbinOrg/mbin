<?php

declare(strict_types=1);

namespace App\EventSubscriber\EntryComment;

use App\Entity\EntryComment;
use App\Entity\User;
use App\Event\EntryComment\EntryCommentBeforeDeletedEvent;
use App\Event\EntryComment\EntryCommentBeforePurgeEvent;
use App\Event\EntryComment\EntryCommentDeletedEvent;
use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Message\Notification\EntryCommentDeletedNotificationMessage;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

class EntryCommentDeleteSubscriber implements EventSubscriberInterface
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
            EntryCommentDeletedEvent::class => 'onEntryCommentDeleted',
            EntryCommentBeforePurgeEvent::class => 'onEntryCommentBeforePurge',
            EntryCommentBeforeDeletedEvent::class => 'onEntryCommentBeforeDelete',
        ];
    }

    public function onEntryCommentDeleted(EntryCommentDeletedEvent $event): void
    {
        $this->cache->invalidateTags(['entry_comment_'.$event->comment->root?->getId() ?? $event->comment->getId()]);

        $this->bus->dispatch(new EntryCommentDeletedNotificationMessage($event->comment->getId()));
    }

    public function onEntryCommentBeforePurge(EntryCommentBeforePurgeEvent $event): void
    {
        $this->onEntryCommentBeforeDeleteImpl($event->user, $event->comment);
    }

    public function onEntryCommentBeforeDelete(EntryCommentBeforeDeletedEvent $event): void
    {
        $this->onEntryCommentBeforeDeleteImpl($event->user, $event->comment);
    }

    public function onEntryCommentBeforeDeleteImpl(?User $user, EntryComment $comment): void
    {
        $this->cache->invalidateTags(['entry_comment_'.$comment->root?->getId() ?? $comment->getId()]);

        $this->bus->dispatch(new EntryCommentDeletedNotificationMessage($comment->getId()));

        if (!$comment->apId || !$comment->magazine->apId || (null !== $user && $comment->magazine->userIsModerator($user))) {
            $activity = $this->deleteWrapper->adjustDeletePayload($user, $comment);
            $payload = $this->activityJsonBuilder->buildActivityJson($activity);
            $this->bus->dispatch(new DeleteMessage($payload, $comment->user->getId(), $comment->magazine->getId()));
        }
    }
}

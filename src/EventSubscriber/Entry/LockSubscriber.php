<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryLockEvent;
use App\Event\Entry\PostLockEvent;
use App\Message\ActivityPub\Outbox\LockMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class LockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryLockEvent::class => 'onEntryLock',
            PostLockEvent::class => 'onPostLock',
        ];
    }

    public function onEntryLock(EntryLockEvent $event): void
    {
        if ($event->actor && null === $event->actor->apId && ($event->entry->magazine->userIsModerator($event->actor) || $event->entry->user === $event->actor)) {
            $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryLockMessage', ['e' => $event->entry->title, 'p' => $event->entry->isLocked ? 'locked' : 'unlocked', 'u' => $event->actor?->username ?? 'system']);
            $this->bus->dispatch(new LockMessage($event->actor->getId(), $event->entry->getId(), null));
        } elseif (null === $event->entry->magazine->apId && $event->actor && ($event->entry->magazine->userIsModerator($event->actor) || $event->entry->user === $event->actor)) {
            if (null !== $event->actor->apId) {
                // do not do the announce of the lock here, but in the LockHandler instead
            } else {
                $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryLockMessage', ['e' => $event->entry->title, 'p' => $event->entry->sticky ? 'locked' : 'unlocked', 'u' => $event->actor?->username ?? 'system']);
                $this->bus->dispatch(new LockMessage($event->actor->getId(), $event->entry->getId(), null));
            }
        }
    }

    public function onPostLock(PostLockEvent $event): void
    {
        if ($event->actor && null === $event->actor->apId && ($event->post->magazine->userIsModerator($event->actor) || $event->post->user === $event->actor)) {
            $this->logger->debug('post {e} got {p} by {u}, dispatching new EntryLockMessage', ['e' => $event->post->getShortTitle(), 'p' => $event->post->isLocked ? 'locked' : 'unlocked', 'u' => $event->actor?->username ?? 'system']);
            $this->bus->dispatch(new LockMessage($event->actor->getId(), null, $event->post->getId()));
        } elseif (null === $event->post->magazine->apId && $event->actor && ($event->post->magazine->userIsModerator($event->actor) || $event->post->user === $event->actor)) {
            if (null !== $event->actor->apId) {
                // do not do the announce of the lock here, but in the LockHandler instead
            } else {
                $this->logger->debug('post {e} got {p} by {u}, dispatching new EntryLockMessage', ['e' => $event->post->getShortTitle(), 'p' => $event->post->sticky ? 'locked' : 'unlocked', 'u' => $event->actor?->username ?? 'system']);
                $this->bus->dispatch(new LockMessage($event->actor->getId(), null, $event->post->getId()));
            }
        }
    }
}

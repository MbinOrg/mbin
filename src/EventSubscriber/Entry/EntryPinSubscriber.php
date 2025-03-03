<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryPinEvent;
use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\EntryPinMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntryPinSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly AddRemoveFactory $addRemoveFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryPinEvent::class => 'onEntryPin',
        ];
    }

    public function onEntryPin(EntryPinEvent $event): void
    {
        if ($event->actor && null === $event->actor->apId && $event->entry->magazine->userIsModerator($event->actor)) {
            $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryPinMessage', ['e' => $event->entry->title, 'p' => $event->entry->sticky ? 'pinned' : 'unpinned', 'u' => $event->actor?->username ?? 'system']);
            $this->bus->dispatch(new EntryPinMessage($event->entry->getId(), $event->entry->sticky, $event->actor?->getId()));
        } elseif (null === $event->entry->magazine->apId && $event->actor && $event->entry->magazine->userIsModerator($event->actor)) {
            if (null !== $event->actor->apId) {
                // do not do the announce of the pin here, but in the AddHandler instead
            } else {
                $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryPinMessage', ['e' => $event->entry->title, 'p' => $event->entry->sticky ? 'pinned' : 'unpinned', 'u' => $event->actor?->username ?? 'system']);
                $this->bus->dispatch(new EntryPinMessage($event->entry->getId(), $event->entry->sticky, $event->actor?->getId()));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryPinEvent;
use App\Message\ActivityPub\Outbox\EntryPinMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntryPinSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
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
        if (null === $event->entry->magazine->apId || ($event->actor && null === $event->actor->apId && $event->entry->magazine->userIsModerator($event->actor))) {
            $this->bus->dispatch(new EntryPinMessage($event->entry->getId(), $event->entry->sticky, $event->actor?->getId()));
        }
    }
}

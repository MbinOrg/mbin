<?php

declare(strict_types=1);

namespace App\EventSubscriber\ActivityPub;

use App\Event\Magazine\MagazineModeratorAddedEvent;
use App\Event\Magazine\MagazineModeratorRemovedEvent;
use App\Message\ActivityPub\Outbox\AddMessage;
use App\Message\ActivityPub\Outbox\RemoveMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MagazineModeratorAddedRemovedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function onModeratorAdded(MagazineModeratorAddedEvent $event): void
    {
        // if the magazine is local then we have authority over it, otherwise the addedBy user has to be a local user
        if (!$event->magazine->apId or (null !== $event->addedBy and !$event->addedBy->apId)) {
            $this->bus->dispatch(new AddMessage($event->addedBy->getId(), $event->magazine->getId(), $event->user->getId()));
        }
    }

    public function onModeratorRemoved(MagazineModeratorRemovedEvent $event): void
    {
        // if the magazine is local then we have authority over it, otherwise the removedBy user has to be a local user
        if (!$event->magazine->apId or (null !== $event->removedBy and !$event->removedBy->apId)) {
            $this->bus->dispatch(new RemoveMessage($event->removedBy->getId(), $event->magazine->getId(), $event->user->getId()));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MagazineModeratorAddedEvent::class => 'onModeratorAdded',
            MagazineModeratorRemovedEvent::class => 'onModeratorRemoved',
        ];
    }
}

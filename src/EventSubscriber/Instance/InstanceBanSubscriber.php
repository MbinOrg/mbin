<?php

declare(strict_types=1);

namespace App\EventSubscriber\Instance;

use App\Event\Instance\InstanceBanEvent;
use App\Message\ActivityPub\Outbox\BlockMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class InstanceBanSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            InstanceBanEvent::class => 'onInstanceBan',
        ];
    }

    public function onInstanceBan(InstanceBanEvent $event): void
    {
        if (!$event->bannedUser->apId && !$event->bannedByUser->apId) {
            // local user banning another local user
            $this->bus->dispatch(new BlockMessage(magazineBanId: null, bannedUserId: $event->bannedUser->getId(), actor: $event->bannedByUser->getId()));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber\Magazine;

use App\Event\Magazine\MagazineBanEvent;
use App\Message\ActivityPub\Outbox\BlockMessage;
use App\Message\Notification\MagazineBanNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MagazineBanSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MagazineBanEvent::class => 'onBan',
        ];
    }

    public function onBan(MagazineBanEvent $event): void
    {
        $this->bus->dispatch(new MagazineBanNotificationMessage($event->ban->getId()));
        $this->logger->debug('[MagazineBanSubscriber::onBan] got ban event: banned: {u}, magazine {m}, expires: {e}, bannedBy: {u2}', [
            'u' => $event->ban->user->username,
            'm' => $event->ban->magazine->name,
            'e' => $event->ban->expiredAt,
            'u2' => $event->ban->bannedBy->username,
        ]);
        if (null !== $event->ban->bannedBy && null === $event->ban->bannedBy->apId) {
            // bannedBy not null and a local user
            $this->bus->dispatch(new BlockMessage(magazineBanId: $event->ban->getId(), bannedUserId: null, actor: null));
        }
    }
}

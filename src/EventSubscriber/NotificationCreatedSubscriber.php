<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\NotificationCreatedEvent;
use App\Service\Notification\UserPushSubscriptionManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserPushSubscriptionManager $pushSubscriptionManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [NotificationCreatedEvent::class => 'onNotificationCreated'];
    }

    public function onNotificationCreated(NotificationCreatedEvent $event): void
    {
        try {
            $this->pushSubscriptionManager->sendTextToUser($event->notification->user, $event->notification);
        } catch (\ErrorException $e) {
            $this->logger->error('there was an exception while sending a {t} to {u}. {e} - {m}', [
                't' => \get_class($event->notification),
                'u' => $event->notification->user->username,
                'e' => \get_class($e),
                'm' => $e->getMessage(),
            ]);
        }
    }
}

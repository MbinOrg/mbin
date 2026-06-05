<?php

declare(strict_types=1);

namespace App\EventSubscriber\Instance;

use App\Event\InstanceBlockedEvent;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InstanceBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SqlHelpers $sqlHelpers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [InstanceBlockedEvent::class => 'handleInstanceBlockedEvent'];
    }

    public function handleInstanceBlockedEvent(InstanceBlockedEvent $event): void
    {
        $this->sqlHelpers->clearCachedUserInstanceBlocks($event->user);
    }
}

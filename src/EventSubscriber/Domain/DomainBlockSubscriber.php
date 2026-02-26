<?php

declare(strict_types=1);

namespace App\EventSubscriber\Domain;

use App\Event\DomainBlockedEvent;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DomainBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SqlHelpers $sqlHelpers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [DomainBlockedEvent::class => 'handleDomainBlockedEvent'];
    }

    public function handleDomainBlockedEvent(DomainBlockedEvent $event): void
    {
        $this->sqlHelpers->clearCachedUserDomainBlocks($event->user);
    }
}

<?php

namespace App\EventSubscriber\Hashtag;

use App\Event\DomainBlockedEvent;
use App\Event\HashtagBlockChangedEvent;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HashtagBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SqlHelpers $sqlHelpers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [HashtagBlockChangedEvent::class => 'handleHashtagBlockChangedEvent'];
    }

    public function handleHashtagBlockChangedEvent(HashtagBlockChangedEvent $event): void
    {
        $this->sqlHelpers->clearCachedUserHashtagBlocks($event->user);
    }
}
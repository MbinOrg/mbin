<?php

declare(strict_types=1);

namespace App\EventSubscriber\Hashtag;

use App\Event\DomainSubscribedEvent;
use App\Event\HashtagSubscriptionChangedEvent;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class HashtagFollowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SqlHelpers $sqlHelpers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [HashtagSubscriptionChangedEvent::class => 'handleHashtagSubscriptionChangedEvent'];
    }

    public function handleHashtagSubscriptionChangedEvent(HashtagSubscriptionChangedEvent $event): void
    {
        $this->sqlHelpers->clearCachedUserSubscribedHashtags($event->user);
    }
}

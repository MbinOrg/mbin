<?php

declare(strict_types=1);

namespace App\EventSubscriber\Domain;

use App\Event\DomainSubscribedEvent;
use App\Repository\ContentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DomainFollowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [DomainSubscribedEvent::class => 'handleDomainSubscribedEvent'];
    }

    public function handleDomainSubscribedEvent(DomainSubscribedEvent $event): void
    {
        $this->contentRepository->clearCachedUserSubscribedDomains($event->user);
    }
}

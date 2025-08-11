<?php

declare(strict_types=1);

namespace App\EventSubscriber\Domain;

use App\Event\DomainBlockedEvent;
use App\Repository\ContentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DomainBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [DomainBlockedEvent::class => 'handleDomainBlockedEvent'];
    }

    public function handleDomainBlockedEvent(DomainBlockedEvent $event): void
    {
        $this->contentRepository->clearCachedUserDomainBlocks($event->user);
    }
}

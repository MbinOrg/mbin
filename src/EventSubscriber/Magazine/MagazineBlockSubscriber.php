<?php

declare(strict_types=1);

namespace App\EventSubscriber\Magazine;

use App\Event\Magazine\MagazineBlockedEvent;
use App\Repository\ContentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MagazineBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [MagazineBlockedEvent::class => 'handleMagazineBlockedEvent'];
    }

    public function handleMagazineBlockedEvent(MagazineBlockedEvent $event): void
    {
        $this->contentRepository->clearCachedUserMagazineBlocks($event->user);
    }
}

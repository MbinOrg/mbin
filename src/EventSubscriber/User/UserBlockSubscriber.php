<?php

declare(strict_types=1);

namespace App\EventSubscriber\User;

use App\Event\User\UserBlockEvent;
use App\Repository\ContentRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ContentRepository $contentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserBlockEvent::class => 'onUserBlock',
        ];
    }

    public function onUserBlock(UserBlockEvent $event): void
    {
        $this->contentRepository->clearCachedUserBlocks($event->blocker);
    }
}

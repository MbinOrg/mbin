<?php

declare(strict_types=1);

namespace App\EventSubscriber\User;

use App\Event\User\UserBlockEvent;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SqlHelpers $sqlHelpers,
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
        $this->sqlHelpers->clearCachedUserBlocks($event->blocker);
    }
}

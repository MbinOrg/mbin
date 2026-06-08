<?php

declare(strict_types=1);

namespace App\EventSubscriber\Instance;

use App\Event\InstancesGlobalBlockedEvent;
use App\Repository\UserRepository;
use App\Utils\SqlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class InstancesGlobalBlockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SqlHelpers $sqlHelpers,
        private UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [InstancesGlobalBlockedEvent::class => 'handleInstancesBlockedEvent'];
    }

    public function handleInstancesBlockedEvent(InstancesGlobalBlockedEvent $event): void
    {
        $fanta = $this->userRepository->findAllPaginated(1, onlyLocal: true, onlyVerified: false, onlyApproved: false, onlyVisible: false, excludeBanned: false, excludeDeleted: false);
        foreach ($fanta->autoPagingIterator() as $user) {
            $this->sqlHelpers->clearCachedUserInstanceBlocks($user);
        }
    }
}

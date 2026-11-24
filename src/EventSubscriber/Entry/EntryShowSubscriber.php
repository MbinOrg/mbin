<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Entity\Entry;
use App\Entity\Notification;
use App\Event\Entry\EntryHasBeenSeenEvent;
use App\Repository\NotificationRepository;
use App\Utils\ArrayUtils;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntryShowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[ArrayShape([EntryHasBeenSeenEvent::class => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [
            EntryHasBeenSeenEvent::class => 'onShowEntry',
        ];
    }

    public function onShowEntry(EntryHasBeenSeenEvent $event): void
    {
        $this->readMessage($event->entry);
    }

    private function readMessage(Entry $entry): void
    {
        if (!$this->security->getUser()) {
            return;
        }

        $notifications = $this->repository->findUnreadEntryNotifications($this->security->getUser(), $entry);

        if (!ArrayUtils::count($notifications)) {
            return;
        }

        ArrayUtils::foreach($notifications, fn ($notification) => $notification->status = Notification::STATUS_READ);

        $this->entityManager->flush();
    }
}

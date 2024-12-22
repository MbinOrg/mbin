<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\MagazineBan;
use App\Entity\MagazineBanNotification;
use App\Event\NotificationCreatedEvent;
use App\Repository\MagazineBanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MagazineBanNotificationManager
{
    use NotificationTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MagazineBanRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function send(MagazineBan $ban): void
    {
        $notification = new MagazineBanNotification($ban->user, $ban);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new NotificationCreatedEvent($notification));
    }
}

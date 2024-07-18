<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Message;
use App\Entity\MessageNotification;
use App\Entity\User;
use App\Event\NotificationCreatedEvent;
use App\Factory\MagazineFactory;
use App\Repository\MagazineSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mercure\HubInterface;

class MessageNotificationManager
{
    use NotificationTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MagazineSubscriptionRepository $repository,
        private readonly MagazineFactory $magazineFactory,
        private readonly HubInterface $publisher,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function send(Message $message, User $sender): void
    {
        $thread = $message->thread;
        $usersToNotify = $thread->getOtherParticipants($sender);

        foreach ($usersToNotify as $subscriber) {
            $notification = new MessageNotification($subscriber, $message);
            $this->entityManager->persist($notification);
            $this->eventDispatcher->dispatch(new NotificationCreatedEvent($notification));
        }

        $this->entityManager->flush();
    }
}

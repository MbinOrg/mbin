<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contracts\ContentInterface;
use App\Entity\MagazineBan;
use App\Entity\Message;
use App\Entity\MessageNotification;
use App\Entity\Notification;
use App\Entity\User;
use App\Service\Notification\MagazineBanNotificationManager;
use App\Service\Notification\MessageNotificationManager;
use Doctrine\ORM\EntityManagerInterface;

class NotificationManager
{
    public function __construct(
        private readonly NotificationManagerTypeResolver $resolver,
        private readonly MessageNotificationManager $messageNotificationManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MagazineBanNotificationManager $magazineBanNotificationManager,
    ) {
    }

    public function sendCreated(ContentInterface $subject): void
    {
        $this->resolver->resolve($subject)->sendCreated($subject);
    }

    public function sendEdited(ContentInterface $subject): void
    {
        $this->resolver->resolve($subject)->sendEdited($subject);
    }

    public function sendDeleted(ContentInterface $subject): void
    {
        $this->resolver->resolve($subject)->sendDeleted($subject);
    }

    public function sendMessageNotification(Message $message, User $sender): void
    {
        $this->messageNotificationManager->send($message, $sender);
    }

    public function sendMagazineBanNotification(MagazineBan $ban): void
    {
        $this->magazineBanNotificationManager->send($ban);
    }

    public function markAllAsRead(User $user): void
    {
        $notifications = $user->getNewNotifications();

        foreach ($notifications as $notification) {
            $notification->status = Notification::STATUS_READ;
        }

        $this->entityManager->flush();
    }

    public function clear(User $user): void
    {
        $notifications = $user->notifications;

        foreach ($notifications as $notification) {
            $this->entityManager->remove($notification);
        }

        $this->entityManager->flush();
    }

    public function readMessageNotification(Message $message, User $user): void
    {
        $repo = $this->entityManager->getRepository(MessageNotification::class);

        $notifications = $repo->findBy(
            [
                'message' => $message,
                'user' => $user,
            ]
        );

        foreach ($notifications as $notification) {
            $notification->status = Notification::STATUS_READ;
        }

        $this->entityManager->flush();
    }

    public function unreadMessageNotification(Message $message, User $user): void
    {
        $repo = $this->entityManager->getRepository(MessageNotification::class);

        $notifications = $repo->findBy(
            [
                'message' => $message,
                'user' => $user,
            ]
        );

        foreach ($notifications as $notification) {
            $notification->status = Notification::STATUS_NEW;
        }

        $this->entityManager->flush();
    }
}

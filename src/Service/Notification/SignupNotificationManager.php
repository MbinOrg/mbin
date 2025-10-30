<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\NewSignupNotification;
use App\Entity\User;
use App\Event\NotificationCreatedEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

readonly class SignupNotificationManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function sendNewSignupNotification(User $newUser): void
    {
        $receiver_admins = $this->userRepository->findAllAdmins();
        $receiver_moderators = $this->userRepository->findAllModerators();
        $receivers = array_merge($receiver_admins, $receiver_moderators);
        foreach ($receivers as $receiver) {
            if (!$receiver->notifyOnUserSignup) {
                continue;
            }
            $notification = new NewSignupNotification($receiver);
            $notification->newUser = $newUser;
            $this->entityManager->persist($notification);
            $this->dispatcher->dispatch(new NotificationCreatedEvent($notification));
        }
        $this->entityManager->flush();
    }
}

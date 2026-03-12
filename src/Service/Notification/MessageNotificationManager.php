<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Message;
use App\Entity\MessageNotification;
use App\Entity\Notification;
use App\Entity\User;
use App\Event\NotificationCreatedEvent;
use App\Factory\MagazineFactory;
use App\Repository\MagazineSubscriptionRepository;
use App\Repository\NotificationRepository;
use App\Service\Contracts\ContentNotificationManagerInterface;
use App\Service\Contracts\SwitchableService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mercure\HubInterface;

class MessageNotificationManager implements SwitchableService, ContentNotificationManagerInterface
{
    use NotificationTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getSupportedTypes(): array
    {
        return [Message::class];
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

    public function sendCreated(ContentInterface $subject): void
    {
        // not supported
    }

    private function notifyMagazine(Notification $notification): void
    {
        // not supported
    }

    public function sendEdited(ContentInterface $subject): void
    {
        // not supported
    }

    public function sendDeleted(ContentInterface $subject): void
    {
        // not supported
    }

    public function purgeNotifications(ContentInterface $subject): void
    {
        if (!$subject instanceof Message) {
            throw new \LogicException();
        }
        $this->notificationRepository->removeMessageNotifications($subject);
    }

    public function purgeMagazineLog(ContentInterface $subject): void
    {
        // not supported
    }
}

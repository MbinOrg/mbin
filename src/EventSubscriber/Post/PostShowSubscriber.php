<?php

declare(strict_types=1);

namespace App\EventSubscriber\Post;

use App\Entity\Notification;
use App\Entity\Post;
use App\Event\Post\PostHasBeenSeenEvent;
use App\Repository\NotificationRepository;
use App\Utils\ArrayUtils;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PostShowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[ArrayShape([PostHasBeenSeenEvent::class => 'string'])]
    public static function getSubscribedEvents(): array
    {
        return [
            PostHasBeenSeenEvent::class => 'onShowEntry',
        ];
    }

    public function onShowEntry(PostHasBeenSeenEvent $event): void
    {
        $this->readMessage($event->post);
    }

    private function readMessage(Post $post): void
    {
        if (!$this->security->getUser()) {
            return;
        }

        $notifications = $this->repository->findUnreadPostNotifications($this->security->getUser(), $post);

        if (!ArrayUtils::count($notifications)) {
            return;
        }

        ArrayUtils::foreach($notifications, fn ($notification) => $notification->status = Notification::STATUS_READ);

        $this->entityManager->flush();
    }
}

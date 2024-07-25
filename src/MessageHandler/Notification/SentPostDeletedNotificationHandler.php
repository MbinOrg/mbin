<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Notification\PostDeletedNotificationMessage;
use App\Repository\PostRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostDeletedNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $repository,
        private readonly NotificationManager $manager
    ) {
    }

    public function __invoke(PostDeletedNotificationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(PostDeletedNotificationMessage $message): void
    {
        $post = $this->repository->find($message->postId);

        if (!$post) {
            throw new UnrecoverableMessageHandlingException('Post not found');
        }

        $this->manager->sendDeleted($post);
    }
}

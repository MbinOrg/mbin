<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Notification\PostCommentCreatedNotificationMessage;
use App\Repository\PostCommentRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostCommentCreatedNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostCommentRepository $repository,
        private readonly NotificationManager $manager
    ) {
    }

    public function __invoke(PostCommentCreatedNotificationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(PostCommentCreatedNotificationMessage $message): void
    {
        $comment = $this->repository->find($message->commentId);

        if (!$comment) {
            throw new UnrecoverableMessageHandlingException('Comment not found');
        }

        $this->manager->sendCreated($comment);
    }
}

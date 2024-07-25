<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Notification\PostCommentDeletedNotificationMessage;
use App\Repository\PostCommentRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostCommentDeletedNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostCommentRepository $repository,
        private readonly NotificationManager $manager
    ) {
    }

    public function __invoke(PostCommentDeletedNotificationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(PostCommentDeletedNotificationMessage $message): void
    {
        $comment = $this->repository->find($message->commentId);

        if (!$comment) {
            throw new UnrecoverableMessageHandlingException('Comment not found');
        }

        $this->manager->sendDeleted($comment);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\EntryCommentEditedNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\EntryCommentRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentEntryCommentEditedNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryCommentRepository $repository,
        private readonly NotificationManager $notificationManager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(EntryCommentEditedNotificationMessage $message)
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof EntryCommentEditedNotificationMessage)) {
            throw new \LogicException();
        }
        $comment = $this->repository->find($message->commentId);

        if (!$comment) {
            throw new UnrecoverableMessageHandlingException('Comment not found');
        }

        $this->notificationManager->sendEdited($comment);
    }
}

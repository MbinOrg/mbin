<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\PostCommentEditedNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\PostCommentRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostCommentEditedNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly PostCommentRepository $repository,
        private readonly NotificationManager $manager,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(PostCommentEditedNotificationMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof PostCommentEditedNotificationMessage)) {
            throw new \LogicException();
        }
        $comment = $this->repository->find($message->commentId);

        if (!$comment) {
            throw new UnrecoverableMessageHandlingException('Comment not found');
        }

        $this->manager->sendEdited($comment);
    }
}

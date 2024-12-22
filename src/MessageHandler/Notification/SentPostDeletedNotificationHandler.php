<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\PostDeletedNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\PostRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostDeletedNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $repository,
        private readonly NotificationManager $notificationManager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(PostDeletedNotificationMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof PostDeletedNotificationMessage)) {
            throw new \LogicException();
        }
        $post = $this->repository->find($message->postId);

        if (!$post) {
            throw new UnrecoverableMessageHandlingException('Post not found');
        }

        $this->notificationManager->sendDeleted($post);
    }
}

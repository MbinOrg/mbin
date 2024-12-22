<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\PostCreatedNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\PostRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentPostCreatedNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostRepository $repository,
        private readonly NotificationManager $manager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(PostCreatedNotificationMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof PostCreatedNotificationMessage)) {
            throw new \LogicException();
        }
        $post = $this->repository->find($message->postId);

        if (!$post) {
            throw new UnrecoverableMessageHandlingException('Post not found');
        }

        $this->manager->sendCreated($post);
    }
}

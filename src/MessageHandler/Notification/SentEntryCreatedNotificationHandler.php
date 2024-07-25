<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Notification\EntryCreatedNotificationMessage;
use App\Repository\EntryRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentEntryCreatedNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryRepository $repository,
        private readonly NotificationManager $manager
    ) {
    }

    public function __invoke(EntryCreatedNotificationMessage $message)
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(EntryCreatedNotificationMessage $message)
    {
        $entry = $this->repository->find($message->entryId);

        if (!$entry) {
            throw new UnrecoverableMessageHandlingException('Entry not found');
        }

        $this->manager->sendCreated($entry);
    }
}

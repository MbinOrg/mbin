<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\EntryEditedNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\EntryRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentEntryEditedNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly EntryRepository $repository,
        private readonly NotificationManager $notificationManager,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(EntryEditedNotificationMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof EntryEditedNotificationMessage)) {
            throw new \LogicException();
        }
        $entry = $this->repository->find($message->entryId);

        if (!$entry) {
            throw new UnrecoverableMessageHandlingException('Entry not found');
        }

        $this->notificationManager->sendEdited($entry);
    }
}

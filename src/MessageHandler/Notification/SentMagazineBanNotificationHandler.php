<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Notification\MagazineBanNotificationMessage;
use App\Repository\MagazineBanRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentMagazineBanNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MagazineBanRepository $repository,
        private readonly NotificationManager $manager
    ) {
    }

    public function __invoke(MagazineBanNotificationMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(MagazineBanNotificationMessage $message): void
    {
        $ban = $this->repository->find($message->banId);

        if (!$ban) {
            throw new UnrecoverableMessageHandlingException('Ban not found');
        }

        $this->manager->sendMagazineBanNotification($ban);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\MagazineBanNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineBanRepository;
use App\Service\NotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentMagazineBanNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly MagazineBanRepository $repository,
        private readonly NotificationManager $manager,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(MagazineBanNotificationMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof MagazineBanNotificationMessage)) {
            throw new \LogicException();
        }
        $ban = $this->repository->find($message->banId);

        if (!$ban) {
            throw new UnrecoverableMessageHandlingException('Ban not found');
        }

        $this->manager->sendMagazineBanNotification($ban);
    }
}

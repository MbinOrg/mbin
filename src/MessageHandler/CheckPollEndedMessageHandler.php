<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckPollEndedMessage;
use App\Message\Contracts\MessageInterface;
use App\Repository\PollRepository;
use App\Service\Notification\PollNotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CheckPollEndedMessageHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly PollRepository $pollRepository,
        private readonly PollNotificationManager $notificationManager,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        KernelInterface $kernel,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(CheckPollEndedMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof CheckPollEndedMessage) {
            throw new \LogicException();
        }

        foreach ($this->pollRepository->getAllEndedPollsToSentNotifications() as $poll) {
            try {
                $this->logger->debug('Sending notifications for poll {p}', ['p' => $poll->getId()]);
                $this->notificationManager->sendPollEndedNotification($poll);
                $poll->sentNotifications = true;
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                $this->logger->error('An error occurred while sending the notifications for the ended poll {p}: {e} - {m}', [
                    'p' => $poll->getId(),
                    'e' => \get_class($exception),
                    'm' => $exception->getMessage(),
                ]);
            }
        }
    }
}

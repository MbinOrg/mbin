<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Message\Contracts\MessageInterface;
use App\Message\Notification\SentNewSignupNotificationMessage;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\UserRepository;
use App\Service\Notification\SignupNotificationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class SentNewSignupNotificationHandler extends MbinMessageHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        private readonly UserRepository $userRepository,
        private readonly SignupNotificationManager $signupNotificationManager,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(SentNewSignupNotificationMessage $message)
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof SentNewSignupNotificationMessage)) {
            throw new \LogicException();
        }
        $user = $this->userRepository->findOneBy(['id' => $message->userId]);
        if (!$user) {
            throw new UnrecoverableMessageHandlingException('user not found');
        }

        if (!$user->isAccountDeleted() && !$user->isSoftDeleted() && null === $user->markedForDeletionAt) {
            // only send notifications for new accounts if the account is not deleted,
            // this is necessary because we create dummy accounts to block the username when an account is deleted
            $this->signupNotificationManager->sendNewSignupNotification($user);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Scheduler\Handlers;

use App\Message\DeleteUserMessage;
use App\Scheduler\Messages\ClearDeletedUserMessage;
use App\Service\UserManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ClearDeletedUserHandler
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ClearDeletedUserMessage $message): void
    {
        $users = $this->userManager->getUsersMarkedForDeletionBefore();
        foreach ($users as $user) {
            try {
                $this->bus->dispatch(new DeleteUserMessage($user->getId()));
            } catch (\Exception|\Error $e) {
                $this->logger->error("couldn't delete user {user}: {message}", ['user' => $user->username, 'message' => \get_class($e).': '.$e->getMessage()]);
            }
        }
    }
}

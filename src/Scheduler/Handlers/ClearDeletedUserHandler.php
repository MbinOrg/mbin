<?php

declare(strict_types=1);

namespace App\Scheduler\Handlers;

use App\Message\DeleteUserMessage;
use App\Scheduler\Messages\ClearDeletedUserMessage;
use App\Service\UserManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ClearDeletedUserHandler
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ClearDeletedUserMessage $message): void
    {
        $users = $this->userManager->getUsersMarkedForDeletionBefore();
        foreach ($users as $user) {
            $this->bus->dispatch(new DeleteUserMessage($user->getId()));
        }
    }
}

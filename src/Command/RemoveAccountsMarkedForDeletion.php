<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\DeleteUserMessage;
use App\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'mbin:users:remove-marked-for-deletion',
    description: 'removes all accounts that are marked for deletion today or in the past.',
)]
class RemoveAccountsMarkedForDeletion extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UserManager $userManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userManager->getUsersMarkedForDeletionBefore();
        $deletedUsers = 0;
        foreach ($users as $user) {
            $output->writeln("deleting $user->username");
            try {
                $this->bus->dispatch(new DeleteUserMessage($user->getId()));
                ++$deletedUsers;
            } catch (\Exception|\Error $e) {
                $output->writeln('an error occurred during the deletion of '.$user->username.': '.\get_class($e).' - '.$e->getMessage());
            }
        }
        $output->writeln("deleted $deletedUsers user");

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\DeleteUserMessage;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'mbin:user:delete',
    description: 'This command will delete the supplied user',
)]
class DeleteUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::REQUIRED, 'The name of the user that should be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userArg = $input->getArgument('user');
        $user = $this->repository->findOneByUsername($userArg);

        if (null !== $user) {
            $this->bus->dispatch(new DeleteUserMessage($user->getId()));
            $io->success('Dispatched a user delete message, the user will be deleted shortly');

            return Command::SUCCESS;
        } else {
            $io->error("There is no user with the username '$userArg'");

            return Command::INVALID;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:user:unsub',
    description: 'Removes all followers from a user',
)]
class UserUnsubCommand extends Command
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserManager $manager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = $this->repository->findOneByUsername($input->getArgument('username'));

        if ($user) {
            foreach ($user->followers as $follower) {
                $this->manager->unfollow($follower->follower, $user);
            }

            $io->success('User unsubscribed');

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}

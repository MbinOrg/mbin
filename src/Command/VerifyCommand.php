<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:user:verify',
    description: 'This command allows you to activate or deactivate a user.',
)]
class VerifyCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $repository,
        private readonly UserManager $manager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED)
            ->addOption('activate', 'a', InputOption::VALUE_NONE, 'Activate user')
            ->addOption('deactivate', 'd', InputOption::VALUE_NONE, 'Deactivate user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $activate = $input->getOption('activate');
        $deactivate = $input->getOption('deactivate');
        $user = $this->repository->findOneByUsername($input->getArgument('username'));

        if (!$user) {
            $io->error('User does not exist.');

            return Command::FAILURE;
        }

        if (!$activate && !$deactivate) {

            if ($user->isVerified) {
                $io->success('The provided user is verified.');
            }
            else {
                $io->success('The provided user is unverified.');
            }

            return Command::SUCCESS;
        }

        if ($activate) {
            $user->isVerified = true;
            $this->entityManager->flush();

            $io->success('The user has been activated.');
        } elseif ($deactivate) {
            $user->isVerified = false;
            $this->entityManager->flush();

            $io->success('The user has been deactivated.');
        }

        return Command::SUCCESS;
    }
}

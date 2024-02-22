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
    name: 'mbin:test',
    description: 'This command will allow you to update remote user info.',
)]
class TestCommand extends Command
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::OPTIONAL, 'Argument description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userArg = $input->getArgument('user');

        $user = $this->userRepository->findOneByUsername($userArg);
        if ($user) {
            print_r($this->userManager->getAllInboxesOfInteractions($user));
            print_r($this->userManager->getAllImagesOfUser($user));
        } else {
            echo "there is no such user\n";
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\MagazineDto;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\MagazineManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:magazine:create',
    description: 'This command allows you to create, delete and purge magazines.',
)]
class MagazineCreateCommand extends Command
{
    public function __construct(
        private readonly MagazineManager $magazineManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('owner', 'o', InputOption::VALUE_REQUIRED, 'the owner of the magazine')
            ->addOption('remove', 'r', InputOption::VALUE_NONE, 'Remove the magazine')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Purge the magazine')
            ->addOption('restricted', null, InputOption::VALUE_NONE, 'Restrict the creation of threads to moderators')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'the title of the magazine')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'the description of the magazine')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $remove = $input->getOption('remove');
        $purge = $input->getOption('purge');
        $restricted = $input->getOption('restricted');
        $ownerInput = $input->getOption('owner');
        if ($ownerInput) {
            $user = $this->userRepository->findOneByUsername($ownerInput);
            if (null === $user) {
                $io->error(\sprintf('There is no user named: "%s"', $input->getArgument('owner')));

                return Command::FAILURE;
            }
        } else {
            $user = $this->userRepository->findAdmin();
        }
        $magazineName = $input->getArgument('name');
        $existing = $this->magazineRepository->findOneBy(['name' => $magazineName, 'apId' => null]);
        if ($remove || $purge) {
            if (null !== $existing) {
                if ($remove) {
                    $this->magazineManager->delete($existing);
                    $io->success(\sprintf('The magazine "%s" has been removed.', $magazineName));

                    return Command::SUCCESS;
                } else {
                    $this->magazineManager->purge($existing);
                    $io->success(\sprintf('The magazine "%s" has been purged.', $magazineName));

                    return Command::SUCCESS;
                }
            } else {
                $io->error(\sprintf('There is no magazine named: "%s"', $magazineName));

                return Command::FAILURE;
            }
        }

        if (null !== $existing) {
            $io->error(\sprintf('There already is a magazine called "%s"', $magazineName));

            return Command::FAILURE;
        }

        $dto = new MagazineDto();
        $dto->name = $magazineName;
        $dto->title = $input->getOption('title') ?? $magazineName;
        $dto->description = $input->getOption('description');
        $dto->isPostingRestrictedToMods = $restricted;

        $magazine = $this->magazineManager->create($dto, $user, rateLimit: false);
        $io->success(\sprintf('The magazine "%s" was created successfully', $magazine->name));

        return Command::SUCCESS;
    }
}

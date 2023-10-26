<?php

declare(strict_types=1);

namespace App\Command\AwesomeBot;

use App\DTO\MagazineDto;
use App\Repository\UserRepository;
use App\Service\MagazineManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'kbin:awesome-bot:magazine:create')]
class AwesomeBotMagazine extends Command
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly MagazineManager $magazineManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('This command allows you to create awesome-bot magazine.')
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('magazine_name', InputArgument::REQUIRED)
            ->addArgument('magazine_title', InputArgument::REQUIRED)
            ->addArgument('url', InputArgument::REQUIRED)
            ->addArgument('tags', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->repository->findOneByUsername($input->getArgument('username'));

        if (!$user) {
            $io->error('User doesn\'t exist.');

            return Command::FAILURE;
        }

        try {
            $dto = new MagazineDto();
            $dto->name = $input->getArgument('magazine_name');
            $dto->title = $input->getArgument('magazine_title');
            $dto->description = 'Powered by '.$input->getArgument('url');
            $dto->setOwner($user);

            $magazine = $this->magazineManager->create($dto, $user);
        } catch (\Exception $e) {
            $io->error('Can\'t create magazine');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

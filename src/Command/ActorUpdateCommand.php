<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\ActivityPub\UpdateActorMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'mbin:actor:update',
    description: 'This command will allow you to update remote actor (user/magazine) info.',
)]
class ActorUpdateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly MagazineRepository $magazineRepository,
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::OPTIONAL, 'AP url of the actor to update')
            ->addOption('users', null, InputOption::VALUE_NONE, 'update *all* known users that needs updating')
            ->addOption('magazines', null, InputOption::VALUE_NONE, 'update *all* known magazines that needs updating')
            ->addOption('force', null, InputOption::VALUE_NONE, 'force actor update even if they are recently updated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userArg = $input->getArgument('user');
        $force = (bool) $input->getOption('force');

        if ($userArg) {
            $this->bus->dispatch(new UpdateActorMessage($userArg, $force));
        } elseif ($input->getOption('users')) {
            foreach ($this->repository->findRemoteForUpdate() as $u) {
                $this->bus->dispatch(new UpdateActorMessage($u->apProfileId, $force));
                $io->info($u->username);
            }
        } elseif ($input->getOption('magazines')) {
            foreach ($this->magazineRepository->findRemoteForUpdate() as $u) {
                $this->bus->dispatch(new UpdateActorMessage($u->apProfileId, $force));
                $io->info($u->name);
            }
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }
}

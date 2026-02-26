<?php

declare(strict_types=1);

namespace App\Command\Update;

use App\Entity\User;
use App\Repository\SearchRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mbin:users:lastActive:update',
    description: 'This command allows set user last active date.'
)]
class UserLastActiveUpdateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SearchRepository $searchRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var UserRepository $repo */
        $repo = $this->entityManager->getRepository(User::class);
        $hideAdult = false;

        foreach ($repo->findAll() as $user) {
            $activity = $this->searchRepository->findUserPublicActivity(1, $user, $hideAdult);
            if ($activity->count()) {
                $user->lastActive = $activity->getCurrentPageResults()[0]->lastActive;
            }
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}

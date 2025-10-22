<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\UpdateWrapper;
use App\Service\DeliverManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:user:private-keys:rotate',
    description: 'This command allows you to manually rotate the private keys of a user or all local users.',
)]
class UserRotatePrivateKeys extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly DeliverManager $deliverManager,
        private readonly UpdateWrapper $updateWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly UserManager $userManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::OPTIONAL)
            ->addOption('all-local-users', 'a');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $all = $input->getOption('all-local-users');

        if (!$username && !$all) {
            $io->error('You must provide a username or execute the command for all local users!');

            return Command::FAILURE;
        }

        if ($username) {
            $user = $this->userRepository->findOneByUsername($username);
            if (!$user) {
                $io->error('The username "'.$username.'" does not exist!');

                return Command::FAILURE;
            } elseif ($user->apId) {
                $io->error('The user "'.$username.'" is not a local user!');

                return Command::FAILURE;
            }
            $users = [$user];
        } elseif ($all) {
            // all local users, including suspended, banned and marked for deletion, but excluding deleted ones
            $users = $this->userRepository->findBy(['apId' => null, 'isDeleted' => false]);
        } else {
            // unreachable because of the first if
            throw new \LogicException('no username is set and it should not run for all local users!');
        }

        $userCount = \count($users);
        $progressBar = $io->createProgressBar($userCount);
        foreach ($users as $user) {
            $this->entityManager->beginTransaction();

            $user->rotatePrivateKey();
            $update = $this->updateWrapper->buildForActor($user);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $updateJson = $this->activityJsonBuilder->buildActivityJson($update);
            $inboxes = $this->userManager->getAllInboxesOfInteractions($user);

            // send one signed with the old private key and one signed with the new
            // some software will fetch the newest public key and some will have cached the old one
            $this->deliverManager->deliver($inboxes, $updateJson, useOldPrivateKey: true);
            $this->deliverManager->deliver($inboxes, $updateJson, useOldPrivateKey: false);
            $progressBar->advance();
        }
        $progressBar->finish();

        $io->info('Successfully rotated the private key for '.$userCount.' users. It might take up to 24 hours for other software to get the new public keys.');

        return Command::SUCCESS;
    }
}

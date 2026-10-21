<?php

declare(strict_types=1);

namespace App\Command\Update;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

#[AsCommand(
    name: 'mbin:oauth:clients:upgrade',
    description: 'This command upgrades all registered OAuth clients to migrate legacy settings.'
)]
class OAuthClientsUpgradeCommand extends Command
{
    private PasswordHasherInterface $passwordHasher;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
        $this->passwordHasher = new NativePasswordHasher();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityRepository $repository */
        $repo = $this->entityManager->getRepository(Client::class);

        foreach ($repo->findAll() as $client) {
            $this->migrateClientSecret($client);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    /**
     * In older versions secrets were stored in plaintext. Now they have to be hashed.
     */
    private function migrateClientSecret(Client $client): void
    {
        $secret = $client->getSecret();
        if (null === $secret || str_starts_with($secret, '$')) {
            return;
        }

        $client->setSecret($this->passwordHasher->hash($secret));
    }
}

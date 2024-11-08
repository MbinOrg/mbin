<?php

declare(strict_types=1);

namespace App\Command\Update;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mbin:push:keys:update',
    description: 'This command allows generate keys for push subscriptions.',
)]
class PushKeysUpdateCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $site = $this->siteRepository->findAll();
        if (empty($site)) {
            $site = new Site();
            $this->entityManager->persist($site);
            $this->entityManager->flush();
        }

        $site = $this->siteRepository->findAll()[0];
        if (null === $site->pushPrivateKey && null === $site->pushPublicKey) {
            $keys = VAPID::createVapidKeys();
            $site->pushPublicKey = (string) $keys['publicKey'];
            $site->pushPrivateKey = (string) $keys['privateKey'];

            $this->entityManager->flush();
        }

        return Command::SUCCESS;
    }
}

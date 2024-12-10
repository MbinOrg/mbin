<?php

declare(strict_types=1);

namespace App\Command\Update;

use App\Repository\DomainRepository;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    'mbin:update:local-domain',
    'This command removes remote entries from the local domain',
)]
class RemoveRemoteEntriesFromLocalDomainCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainRepository $repository,
        private readonly SettingsManager $settingsManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $domainName = 'https://'.$this->settingsManager->get('KBIN_DOMAIN');
        $domainName = preg_replace('/^www\./i', '', parse_url($domainName)['host']);

        $domain = $this->repository->findOneByName($domainName);
        if (!$domain) {
            $io->warning(\sprintf('There is no local domain like %s', $domainName));

            return Command::SUCCESS;
        }

        $countBeforeSql = 'SELECT COUNT(*) as ctn FROM entry WHERE domain_id = :dId';
        $stmt1 = $this->entityManager->getConnection()->prepare($countBeforeSql);
        $countBefore = \intval($stmt1->executeQuery(['dId' => $domain->getId()])->fetchOne());

        $sql = 'UPDATE entry SET domain_id = NULL WHERE domain_id = :dId AND ap_id IS NOT NULL';
        $stmt2 = $this->entityManager->getConnection()->prepare($sql);
        $stmt2->executeStatement(['dId' => $domain->getId()]);

        $countAfterSql = 'SELECT COUNT(*) as ctn FROM entry WHERE domain_id = :dId';
        $stmt3 = $this->entityManager->getConnection()->prepare($countAfterSql);
        $countAfter = \intval($stmt3->executeQuery(['dId' => $domain->getId()])->fetchOne());

        $sql = 'UPDATE domain SET entry_count = :c WHERE id = :dId';
        $stmt4 = $this->entityManager->getConnection()->prepare($sql);
        $stmt4->executeStatement(['c' => $countAfter, 'dId' => $domain->getId()]);

        $io->success(\sprintf('Removed %d entries from the domain %s, now only %d entries are left', $countBefore - $countAfter, $domainName, $countAfter));

        return Command::SUCCESS;
    }
}

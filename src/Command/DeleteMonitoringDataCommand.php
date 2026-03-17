<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:monitoring:delete-data',
    description: 'Delete all Monitoring Data',
)]
class DeleteMonitoringDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Delete all monitoring data');
        $this->addOption('queries', null, InputOption::VALUE_NONE, 'Delete all query data');
        $this->addOption('twig', null, InputOption::VALUE_NONE, 'Delete all twig data');
        $this->addOption('requests', null, InputOption::VALUE_NONE, 'Delete all request data');
        $this->addOption('before', null, InputOption::VALUE_OPTIONAL, 'Limit the deletion to contexts before the date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $beforeString = $input->getOption('before');

        try {
            $before = $beforeString ? new \DateTimeImmutable($beforeString) : new \DateTimeImmutable();
        } catch (\Exception $e) {
            $io->error(\sprintf('%s is not in a valid form', $input->getOption('before')));

            return Command::FAILURE;
        }

        if ($input->getOption('all')) {
            $stmt = $this->entityManager->getConnection()->prepare('DELETE FROM monitoring_execution_context WHERE created_at < :before');
            $stmt->bindValue('before', $before, 'datetime_immutable');
            $stmt->executeStatement();

            $io->success('Deleted monitoring data before '.$before->format(DATE_ATOM));
        } else {
            if ($input->getOption('queries')) {
                $stmt = $this->entityManager->getConnection()->prepare('DELETE FROM monitoring_query WHERE created_at < :before');
                $stmt->bindValue('before', $before, 'datetime_immutable');
                $stmt->executeStatement();
                $io->success('Deleted query data before '.$before->format(DATE_ATOM));
            }

            if ($input->getOption('twig')) {
                $stmt = $this->entityManager->getConnection()->prepare('DELETE FROM monitoring_twig_render WHERE created_at < :before');
                $stmt->bindValue('before', $before, 'datetime_immutable');
                $stmt->executeStatement();
                $io->success('Deleted twig data before '.$before->format(DATE_ATOM));
            }

            if ($input->getOption('requests')) {
                $this->entityManager->getConnection()->prepare('DELETE FROM monitoring_curl_request WHERE created_at < :before');
                $stmt->bindValue('before', $before, 'datetime_immutable');
                $stmt->executeStatement();
                $io->success('Deleted request data before '.$before->format(DATE_ATOM));
            }
        }

        return Command::SUCCESS;
    }
}

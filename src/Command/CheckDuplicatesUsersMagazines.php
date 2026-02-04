<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:check:duplicates-users-magazines',
    description: 'Check for duplicate users and magazines.',
)]
class CheckDuplicatesUsersMagazines extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform: check or delete')
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity type: users or magazines')
            ->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'IDs to delete (comma-separated)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $entity = $input->getArgument('entity');

        if (!\in_array($entity, ['users', 'magazines'])) {
            $io->error('Invalid entity type. Use "users" or "magazines"');

            return Command::FAILURE;
        }

        if ('check' === $action) {
            return $this->checkDuplicates($io, $entity);
        } elseif ('delete' === $action) {
            $ids = $input->getArgument('ids');
            if (empty($ids)) {
                $io->error('Please provide IDs to delete');

                return Command::FAILURE;
            }

            return $this->deleteEntities($io, $entity, $ids);
        } else {
            $io->error('Invalid action. Use "check" or "delete"');

            return Command::FAILURE;
        }
    }

    private function checkDuplicates(SymfonyStyle $io, string $entity): int
    {
        $conn = $this->entityManager->getConnection();

        if ('users' === $entity) {
            $sql = '
                SELECT id, username, ap_public_url, created_at, last_active 
                FROM "user" u2 
                WHERE EXISTS (
                    SELECT COUNT(*), ap_public_url 
                    FROM "user" 
                    WHERE ap_id IS NOT NULL 
                    AND ap_public_url = u2.ap_public_url 
                    GROUP BY ap_public_url 
                    HAVING COUNT(*) > 1
                )
                ORDER BY ap_public_url, created_at
            ';
            $entityName = 'User';
            $nameField = 'username';
        } else { // magazines
            $sql = '
                SELECT id, name, ap_public_url, created_at, last_active 
                FROM magazine m2 
                WHERE EXISTS (
                    SELECT COUNT(*), ap_public_url 
                    FROM magazine 
                    WHERE ap_id IS NOT NULL 
                    AND ap_public_url = m2.ap_public_url 
                    GROUP BY ap_public_url 
                    HAVING COUNT(*) > 1
                )
                ORDER BY ap_public_url, created_at
            ';
            $entityName = 'Magazine';
            $nameField = 'name';
        }

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery();
        $results = $stmt->fetchAllAssociative();

        if (empty($results)) {
            $io->success("No duplicate {$entityName}s found.");

            return Command::SUCCESS;
        }

        $this->displayDuplicatesTable($io, $results, $entityName, $nameField);

        $io->section('Deletion Instructions');
        $io->text("To delete specific {$entityName}s, run:");
        $io->text("./bin/console mbin:check:duplicates-users-magazines delete {$entity} id1,id2,id3");

        return Command::SUCCESS;
    }

    private function displayDuplicatesTable(SymfonyStyle $io, array $results, string $entityName, string $nameField): void
    {
        $io->section("Duplicate {$entityName}s Found");

        // Group by ap_public_url
        $duplicates = [];
        foreach ($results as $item) {
            $url = $item['ap_public_url'];
            if (!isset($duplicates[$url])) {
                $duplicates[$url] = [];
            }
            $duplicates[$url][] = $item;
        }

        foreach ($duplicates as $url => $items) {
            $io->text("\n".str_repeat('=', 139));
            $io->text('Duplicate Group: '.$url);
            $io->text(str_repeat('-', 139));

            // Table header
            $io->text(\sprintf(
                '| %-8s | %-80s | %-19s | %-19s |',
                'ID',
                ucfirst($nameField),
                'Created At',
                'Last Active'
            ));
            $io->text(str_repeat('-', 139));

            // Table rows
            foreach ($items as $item) {
                $io->text(\sprintf(
                    '| %-8s | %-80s | %-19s | %-19s |',
                    $item['id'],
                    substr($item[$nameField], 0, 80),
                    $item['created_at'] ? substr($item['created_at'], 0, 19) : 'N/A',
                    $item['last_active'] ? substr($item['last_active'], 0, 19) : 'N/A'
                ));
            }
            $io->text(str_repeat('-', 139));
        }

        $io->text(\sprintf("\nTotal duplicate {$entityName}s: %d", \count($results)));
    }

    private function deleteEntities(SymfonyStyle $io, string $entity, array $ids): int
    {
        $conn = $this->entityManager->getConnection();
        $tableName = 'users' === $entity ? '"user"' : 'magazine';
        $entityName = ucfirst(substr($entity, 0, -1)); // Remove 's' and capitalize
        $nameField = 'users' === $entity ? 'username' : 'name';

        try {
            $conn->beginTransaction();

            foreach ($ids as $id) {
                // First check if entity exists
                $checkSql = "SELECT id, {$nameField} FROM {$tableName} WHERE id = :id";
                $stmt = $conn->prepare($checkSql);
                $stmt = $stmt->executeQuery(['id' => $id]);
                $item = $stmt->fetchAssociative();

                if (!$item) {
                    $io->warning("{$entityName} with ID $id not found, skipping...");
                    continue;
                }

                // Delete the entity
                $deleteSql = "DELETE FROM {$tableName} WHERE id = :id";
                $stmt = $conn->prepare($deleteSql);
                $stmt->executeStatement(['id' => $id]);

                $io->success("Deleted {$entityName}: {$item[$nameField]} (ID: $id)");
            }

            $conn->commit();
            $io->success("{$entityName} deletion completed successfully.");
        } catch (\Exception $e) {
            $conn->rollBack();
            $io->error('Error during deletion: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

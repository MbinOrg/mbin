<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\MagazineManager;
use App\Service\UserManager;
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
        private readonly UserManager $userManager,
        private readonly MagazineManager $magazineManager,
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
            $io->text("\n".str_repeat('=', 30));
            $io->text('Duplicate Group: '.$url);

            // Prepare table data
            $headers = ['ID', ucfirst($nameField), 'Created At', 'Last Active'];
            $rows = [];

            foreach ($items as $item) {
                $rows[] = [
                    $item['id'],
                    $item[$nameField],
                    $item['created_at'] ? substr($item['created_at'], 0, 19) : 'N/A',
                    $item['last_active'] ? substr($item['last_active'], 0, 19) : 'N/A',
                ];
            }

            $io->table($headers, $rows);
        }

        $io->text(\sprintf("\nTotal duplicate {$entityName}s: %d", \count($results)));
    }

    private function deleteEntities(SymfonyStyle $io, string $entity, array $ids): int
    {
        try {
            foreach ($ids as $id) {
                if ('users' === $entity) {
                    // Check if user exists first
                    $existingUser = $this->entityManager->getRepository(User::class)->find($id);
                    if (!$existingUser) {
                        $io->warning("User with ID $id not found, skipping...");
                        continue;
                    }

                    $this->userManager->delete($existingUser);
                    $io->success("Deleted user: {$existingUser->getUsername()} (ID: $id)");
                } else { // magazines
                    // Check if magazine exists first
                    $magazine = $this->entityManager->getRepository(\App\Entity\Magazine::class)->find($id);
                    if (!$magazine) {
                        $io->warning("Magazine with ID $id not found, skipping...");
                        continue;
                    }

                    $this->magazineManager->purge($magazine);
                    $io->success("Deleted magazine: {$magazine->getName()} (ID: $id)");
                }
            }

            $entityName = ucfirst(substr($entity, 0, -1));
            $io->success("{$entityName} deletion completed successfully.");
        } catch (\Exception $e) {
            $io->error('Error during deletion: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

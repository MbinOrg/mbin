<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Magazine;
use App\Entity\User;
use App\Service\MagazineManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            ->setDescription('Check for duplicate users and magazines with interactive deletion options.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Duplicate Users and Magazines Checker');

        // Let user choose entity type
        $entity = $io->choice(
            'What would you like to check for duplicates?',
            ['users' => 'Users', 'magazines' => 'Magazines'],
            'users'
        );

        // Check for duplicates
        $duplicates = $this->findDuplicates($io, $entity);

        if (empty($duplicates)) {
            $entityName = ucfirst(substr($entity, 0, -1));
            $io->success("No duplicate {$entityName}s found.");

            return Command::SUCCESS;
        }

        // Display duplicates table
        $entityName = ucfirst($entity);
        $nameField = 'users' === $entity ? 'username' : 'name';
        $this->displayDuplicatesTable($io, $duplicates, $entityName, $nameField);

        // Ask if user wants to delete any duplicates
        $deleteChoice = $io->confirm('Would you like to delete any of these duplicates?', false);

        if (!$deleteChoice) {
            $io->success('Operation completed. No deletions performed.');

            return Command::SUCCESS;
        }

        // Get IDs to delete
        $idsInput = $io->ask(
            'Enter the IDs to delete (comma-separated, e.g., 1,2,3)',
            null,
            function ($input) {
                if (empty($input)) {
                    throw new \InvalidArgumentException('Please provide at least one ID');
                }

                $ids = array_map('trim', explode(',', $input));
                foreach ($ids as $id) {
                    if (!is_numeric($id)) {
                        throw new \InvalidArgumentException("Invalid ID: $id");
                    }
                }

                return $ids;
            }
        );

        return $this->deleteEntities($io, $entity, $idsInput);
    }

    private function findDuplicates(SymfonyStyle $io, string $entity): array
    {
        $conn = $this->entityManager->getConnection();

        if ('users' === $entity) {
            $sql = '
                SELECT id, username, ap_public_url, created_at, last_active FROM 
                "user" WHERE ap_public_url IN 
                (SELECT ap_public_url FROM "user" WHERE ap_public_url IS NOT NULL GROUP BY ap_public_url HAVING COUNT(*) > 1) 
                ORDER BY ap_public_url;
            ';
        } else { // magazines
            $sql = '
                SELECT id, name, ap_public_url, created_at, last_active FROM 
                "magazine" WHERE ap_public_url IN 
                (SELECT ap_public_url FROM "magazine" WHERE ap_public_url IS NOT NULL GROUP BY ap_public_url HAVING COUNT(*) > 1) 
                ORDER BY ap_public_url; 
            ';
        }

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery();
        $results = $stmt->fetchAllAssociative();

        return $results;
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
                    $magazine = $this->entityManager->getRepository(Magazine::class)->find($id);
                    if (!$magazine) {
                        $io->warning("Magazine with ID $id not found, skipping...");
                        continue;
                    }

                    $this->magazineManager->purge($magazine);
                    $io->success("Deleted magazine: {$magazine->getApName()} (ID: $id)");
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

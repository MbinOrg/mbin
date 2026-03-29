<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Repository\InstanceRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\MagazineManager;
use App\Service\MentionManager;
use App\Service\UserManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
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
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly UserRepository $userRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly InstanceRepository $instanceRepository,
        private readonly MentionManager $mentionManager,
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
        $dryRun = \boolval($input->getOption('dry-run'));

        // Let user choose entity type
        $entity = $io->choice(
            'What would you like to check for duplicates?',
            ['users' => 'Users', 'magazines' => 'Magazines'],
            'users'
        );

        if ('users' === $entity) {
            $userType = $io->choice('Solve duplicates by handle or by profile URL?', ['handle', 'profileUrl']);
            if ('handle' === $userType) {
                $this->fixDuplicatesByHandle($io, $dryRun);

                return Command::SUCCESS;
            }
        }

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

    protected function fixDuplicatesByHandle(SymfonyStyle $io, bool $dryRun): int
    {
        $sql = 'SELECT
    LOWER(username) AS normalized_username,
    COUNT(*)        AS duplicate_count,
    json_agg(id)   AS user_ids,
    json_agg(username) AS usernames,
    json_agg(ap_profile_id) as urls
FROM "user"
WHERE application_status = \'Approved\' AND ap_id IS NOT NULL
GROUP BY LOWER(username)
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC';

        $io->writeln('Gathering duplicate users');
        $result = $this->entityManager->getConnection()->prepare($sql)->executeQuery()->fetchAllAssociative();

        $usersToUpdate = [];
        $usersToMerge = [];
        $io->writeln('Determining which users to update');
        foreach ($result as $row) {
            $username = $this->mentionManager->getUsername($row['normalized_username']);
            $urls = json_decode($row['urls']);
            // if the URL contains the string (after removing the host), the username is probably right
            $urlsToUpdate = array_filter($urls, fn (string $url) => !str_contains(str_replace('https://'.parse_url($url, PHP_URL_HOST), '', strtolower($url)), $username));

            foreach ($urlsToUpdate as $url) {
                $usersToUpdate = array_merge($usersToUpdate, $this->getUsersToUpdateFromUrl($url, $io, $dryRun));
            }

            $referenceUrl = strtolower($urls[0]);
            $pairShouldBeMerged = true;
            foreach ($urls as $url) {
                if ($referenceUrl !== strtolower($url)) {
                    $pairShouldBeMerged = false;
                    break;
                }
            }
            if ($pairShouldBeMerged) {
                $usersToMerge[] = [json_decode($row['user_ids'])];
            }
        }
        $io->writeln('Updating users from URLs: '.implode(', ', $usersToUpdate));
        foreach ($usersToUpdate as $url) {
            if (!$dryRun) {
                $io->writeln("Updating actor $url");
                $this->activityPubManager->updateActor($url);
            } else {
                $io->writeln("Would have updated actor $url");
            }
        }

        foreach ($usersToMerge as $userPairs) {
            $users = $this->userRepository->findBy(['id' => $userPairs]);
            $userString = implode(', ', array_map(fn (User $user) => "'$user->username' ($user->apProfileId)", $users));
            $answer = $io->ask("Should these users get merged: $userString", 'yes');
            if ('yes' === $answer) {
                $this->mergeRemoteUsers($users, $io, $dryRun);
            }
        }

        return Command::SUCCESS;
    }

    private function getUsersToUpdateFromUrl(string $url, SymfonyStyle $io, bool $dryRun): array
    {
        $user = $this->userRepository->findOneBy(['apProfileId' => $url]);
        if (!$user) {
            return [];
        }

        if ($user->isDeleted || $user->isSoftDeleted() || $user->isTrashed() || $user->markedForDeletionAt) {
            // deleted users do not get updated, thus they can cause non-unique violations if they also have a wrong username
            if (!$dryRun) {
                $answer = $io->ask("Do you want to purge user '$user->username' ($user->apProfileId)? They are already deleted.", 'yes');
                if ('yes' === strtolower($answer)) {
                    $this->purgeUser($user);
                }
            } else {
                $io->writeln("Would have asked whether '$user->username' ($user->apProfileId) should be purged");
            }

            return [];
        }

        $instance = $this->instanceRepository->findOneBy(['domain' => $user->apDomain]);
        if ($instance && ($instance->isBanned || $instance->isDead())) {
            if (!$dryRun) {
                $answer = $io->ask("The instance $instance->domain is either dead or banned, should the user '$user->username' ($user->apProfileId) be purged?", 'yes');
                if ('yes' === strtolower($answer)) {
                    $this->purgeUser($user);
                }
            } else {
                $io->writeln("Would have asked whether '$user->username' ($user->apProfileId) should be purged");
            }

            return [];
        }

        $io->writeln("fetching remote object for '$user->username' ($user->apProfileId)");
        $actorObject = $this->apHttpClient->getActorObject($url);
        if (!$actorObject) {
            if (!$dryRun) {
                $this->purgeUser($user);
            } else {
                $io->writeln("Would have purged user '$user->username' ('$user->apProfileId'), because we didn't get a response from the server");
            }

            return [];
        } elseif ('Tombstone' === $actorObject['type']) {
            if (!$dryRun) {
                $this->purgeUser($user);
            } else {
                $io->writeln("Would have purged user '$user->username' ('$user->apProfileId'), because it is deleted on the remote server");
            }

            return [];
        }

        $domain = parse_url($url, PHP_URL_HOST);
        $newUsername = '@'.$actorObject['preferredUsername'].'@'.$domain;
        // if there already is a user with the username that is supposed to be on the current one,
        // we have to update that user before the current one to avoid non-unique violations
        $existingUsers = $this->userRepository->findBy(['username' => $newUsername]);
        $result = [];
        foreach ($existingUsers as $existingUser) {
            if ($existingUser) {
                if ($user->getId() === $existingUser->getId()) {
                    continue;
                }
                $additionalUrls = $this->getUsersToUpdateFromUrl($existingUser->apProfileId, $io, $dryRun);

                $result = array_merge($additionalUrls, $result);
            }
        }
        $result[] = $url;

        return $result;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function purgeUser(User $user): void
    {
        $stmt = $this->entityManager->getConnection()
            ->prepare('DELETE FROM "user" WHERE id = :id');
        $stmt->bindValue('id', $user->getId(), ParameterType::INTEGER);
        $stmt->executeStatement();
    }

    /**
     * This replaces all the references of one user with all the others. The main user the others are merged into
     * is determined by the exact match of the 'id' gathered from the URL in `$user->apProfileId`.
     *
     * @param User[] $users
     *
     * @throws InvalidApPostException
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgumentException
     */
    private function mergeRemoteUsers(array $users, SymfonyStyle $io, bool $dryRun): void
    {
        if (0 === \count($users)) {
            return;
        }

        $actorObject = $this->apHttpClient->getActorObject($users[0]->apProfileId);
        $mainUser = array_first(array_filter($users, fn (User $user) => $user->apProfileId === $actorObject['id']));

        if (!$mainUser) {
            $io->warning(\sprintf('Could not find an exact match for %s in the users %s', $actorObject['id'], implode(', ', array_map(fn (User $user) => $user->apProfileId, $users))));

            return;
        }

        foreach ($users as $user) {
            if ($mainUser->getId() === $user->getId()) {
                continue;
            }

            if ($dryRun) {
                $io->writeln("Would have merged '$user->username' ('$user->apProfileId') into '$mainUser->username' ('$mainUser->apProfileId')");
                continue;
            }

            $io->writeln("Merging '$user->username' ('$user->apProfileId') into '$mainUser->username' ('$mainUser->apProfileId')");
            $conn = $this->entityManager->getConnection();

            $stmt = $conn->prepare('UPDATE activity SET user_actor_id = :main WHERE user_actor_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE entry SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE entry_comment SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE post SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE post_comment SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE entry_vote SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE entry_comment_vote SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE post_vote SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE post_comment_vote SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE favourite SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE message_thread_participants SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE message SET sender_id = :main WHERE sender_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE magazine_block SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE magazine_log SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE magazine_log SET acting_user_id = :main WHERE acting_user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE magazine_subscription SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE moderator SET user_id = :main WHERE user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE moderator SET added_by_user_id = :main WHERE added_by_user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE notification_settings SET target_user_id = :main WHERE target_user_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE report SET reported_id = :main WHERE reported_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE report SET reporting_id = :main WHERE reporting_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_block SET blocker_id = :main WHERE blocker_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_block SET blocked_id = :main WHERE blocked_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_follow SET follower_id = :main WHERE follower_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_follow SET following_id = :main WHERE following_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_follow_request SET follower_id = :main WHERE follower_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $stmt = $conn->prepare('UPDATE user_follow_request SET following_id = :main WHERE following_id = :oldId');
            $stmt->bindValue(':main', $mainUser->getId(), ParameterType::INTEGER);
            $stmt->bindValue(':oldId', $user->getId(), ParameterType::INTEGER);
            $stmt->executeStatement();

            $io->writeln("Purging user '$user->username' ('$user->apProfileId')");
            $this->purgeUser($user);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:messages:remove_and_ban',
    description: 'Removes found direct messages on body search and ban senders.',
)]
class RemoveDMAndBanCommand extends Command
{
    private string $bodySearch;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $repository,
        private readonly UserManager $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('body', InputArgument::REQUIRED, 'Search query for direct message body.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->bodySearch = (string) $input->getArgument('body');

        try {
            // Search and display messages
            $this->searchMessages($io);

            // Confirm?
            if (!$io->confirm('Do you want to remove *all* found messages and ban sender users? This action is irreversible !!!', false)) {
                // If not confirmed, exit
                return Command::FAILURE;
            }

            // Ban sender users
            $io->note('Banning sender users...');
            $this->banSenders();

            // Remove messages
            $io->note('Removing direct messages...');
            $this->removeMessages();
            // Ban sender user
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Search for direct messages matching the search query.
     */
    private function searchMessages(SymfonyStyle $io): void
    {
        $resultSet = $this->entityManager->getConnection()->executeQuery('
            select m.id ,u.username, m.body
            FROM message m
            JOIN public.user u ON m.sender_id = u.id
            WHERE body LIKE :body', ['body' => '%'.$this->bodySearch.'%']);
        $results = $resultSet->fetchAllAssociative();

        if (0 === \count($results)) {
            throw new \Exception('No direct messages found.');
        }

        $io->text('Found '.\count($results).' direct messages.');

        // Display results
        $table = new Table(new ConsoleOutput());
        $table
            ->setHeaders(['DM ID', 'Sender username', 'Body direct message'])
            ->setRows(array_map(fn ($item) => [
                $item['id'],
                $item['username'],
                wordwrap(str_replace(["\r\n", "\r", "\n"], " ", $item['body']), 60, PHP_EOL, true),
            ], $results));
        $table->render();
    }

    /**
     * Ban sender users based on the found messages.
     */
    private function banSenders(): void
    {
        $this->entityManager->getConnection()->executeQuery('
            UPDATE public.user
            SET is_banned = TRUE
            WHERE id IN (
                SELECT DISTINCT m.sender_id
                FROM message m
                JOIN public.user u ON m.sender_id = u.id
                WHERE body LIKE :body
            )', ['body' => '%'.$this->bodySearch.'%']);
    }

    /**
     * Remove messages by removing message threads (message_thread table).
     *
     * Which will automatically do a cascade delete on the messages table and
     * the message participants table.
     */
    private function removeMessages(): void
    {
        $this->entityManager->getConnection()->executeQuery('
            DELETE FROM message_thread
            WHERE id IN (
                SELECT DISTINCT thread_id 
                FROM message 
                WHERE body LIKE :body
            )', ['body' => '%'.$this->bodySearch.'%']);
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mbin:messenger:dead:remove_all',
    description: 'This command removes all dead messages from the dead queue (database).',
)]
class RemoveDeadMessagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->removeDeadMessages();

        return Command::SUCCESS;
    }

    /**
     * Remove all dead messages from database.
     */
    private function removeDeadMessages()
    {
        $this->entityManager->getConnection()->executeQuery(
            'DELETE FROM messenger_messages WHERE queue_name = ?',
            ['dead']
        );

        // Followed by vacuuming the messenger_messages table.
        $this->entityManager->getConnection()->executeQuery('VACUUM messenger_messages');
    }
}

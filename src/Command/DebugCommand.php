<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PollRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('mbin:debug')]
class DebugCommand extends Command
{
    public function __construct(
        private readonly PollRepository $pollRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->pollRepository->getAllEndedPollsToSentNotifications() as $poll) {
            $output->writeln("poll {$poll->getId()}");
            foreach ($this->pollRepository->getAllLocalVotersOfPoll($poll) as $voter) {
                $output->writeln("Voter $voter->username in poll {$poll->getId()}");
            }
        }

        return 0;
    }
}

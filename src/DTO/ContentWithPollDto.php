<?php

declare(strict_types=1);

namespace App\DTO;

use App\DTO\Contracts\PollDtoTrait;
use App\Entity\Poll;
use App\Entity\PollChoice;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class ContentWithPollDto
{
    use PollDtoTrait;

    public function __construct()
    {
        $this->pollEndsAt = new \DateTimeImmutable('now + 7 days');
    }

    public function addEmptyChoices(): void
    {
        $choicesToAdd = 5 - \sizeof($this->choices);
        if ($choicesToAdd <= 0) {
            $choicesToAdd = 1;
        }

        for ($i = 0; $i < $choicesToAdd; ++$i) {
            $this->choices[] = '';
        }
    }

    public function mergePollIntoDto(Poll $poll): void
    {
        $this->addPoll = true;
        $this->pollEndsAt = $poll->endDate;
        $this->isMultipleChoicePoll = $poll->multipleChoice;
        foreach ($poll->choices as $choice) {
            $this->choices[] = $choice->name;
        }
    }

    public function getPollResponseDto(?Poll $poll, ?UserInterface $user): ?PollResponseDto
    {
        if (null === $poll) {
            return null;
        }
        $pollDto = new PollResponseDto();
        $pollDto->endDate = $this->pollEndsAt;
        $pollDto->voterCount = $poll->voterCount;
        $pollDto->currentUserHasVoted = $user instanceof User ? $poll->hasUserVoted($user) : null;
        $pollDto->choices = $poll->choices ? array_map(fn (PollChoice $choice) => PollChoiceResponseDto::createFromPollChoice($choice, $user), $poll->choices->toArray()) : null;
        return $pollDto;
    }
}

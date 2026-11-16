<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Poll;

class PollFactory
{
    public function __construct(
        private readonly PollChoiceNoteFactory $pollChoiceNoteFactory,
    ) {
    }

    public function addToNote(array &$note, Poll $poll): void
    {
        $note['votersCount'] = $poll->voterCount;
        $note['endTime'] = $poll->endDate->format(DATE_ATOM);
        $options = [];
        foreach ($poll->choices as $choice) {
            $options[] = $this->pollChoiceNoteFactory->create($choice);
        }
        if ($poll->multipleChoice) {
            $note['anyOf'] = $options;
        } else {
            $note['oneOf'] = $options;
        }
    }
}

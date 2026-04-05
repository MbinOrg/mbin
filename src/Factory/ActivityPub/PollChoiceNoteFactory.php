<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\PollChoice;
use App\Service\ActivityPub\ContextsProvider;

class PollChoiceNoteFactory
{
    public function __construct(
        private readonly ContextsProvider $contextsProvider,
    ) {
    }

    public function create(PollChoice $pollChoice, bool $includeContext = false): array
    {
        $note = [
            'type' => 'Note',
            'name' => $pollChoice->name,
            'replies' => [
                'type' => 'Collection',
                'totalItems' => $pollChoice->voteCount,
            ],
        ];

        if ($includeContext) {
            $note['@context'] = [
                $this->contextsProvider->referencedContexts(),
            ];
        }

        return $note;
    }
}

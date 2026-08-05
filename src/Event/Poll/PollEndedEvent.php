<?php

declare(strict_types=1);

namespace App\Event\Poll;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Poll;

class PollEndedEvent
{
    public function __construct(
        public Poll $poll,
        public ContentInterface $content,
    ) {
    }
}

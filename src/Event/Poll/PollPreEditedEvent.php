<?php

declare(strict_types=1);

namespace App\Event\Poll;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Poll;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;

class PollPreEditedEvent
{
    public function __construct(
        public Poll $poll,
        public Entry|EntryComment|Post|PostComment $content,
        public User $editor,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class PollVoteMessage implements ActivityPubOutboxInterface
{
    public function __construct(
        public string $voteUuid,
    ) {
    }
}

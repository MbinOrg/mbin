<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class FollowMessage implements ActivityPubOutboxInterface
{
    public function __construct(
        public int $followerId,
        public int $followingId,
        public bool $unfollow = false,
        public bool $magazine = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class LockMessage implements ActivityPubOutboxInterface
{
    public function __construct(
        public int $actorId,
        public ?int $entryId = null,
        public ?int $postId = null,
    ) {
    }
}

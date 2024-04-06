<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class AnnounceLikeMessage implements ActivityPubOutboxInterface
{
    public function __construct(
        public int $userId,
        public int $objectId,
        public string $objectType,
        public bool $undo = false
    ) {
    }
}

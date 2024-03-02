<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\AsyncApMessageInterface;

class AnnounceLikeMessage implements AsyncApMessageInterface
{
    public function __construct(
        public int $userId,
        public int $objectId,
        public string $objectType,
        public bool $undo = false
    ) {
    }
}

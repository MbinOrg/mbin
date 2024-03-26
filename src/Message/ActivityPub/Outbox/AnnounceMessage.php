<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class AnnounceMessage implements ActivityPubOutboxInterface
{
    public function __construct(
        public ?int $userId,
        public ?int $magazineId,
        public int $objectId,
        public string $objectType,
        public bool $removeAnnounce = false
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class EntryPinMessage implements ActivityPubOutboxInterface
{
    public function __construct(public int $entryId, public bool $sticky, public ?int $actorId)
    {
    }
}

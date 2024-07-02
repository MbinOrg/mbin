<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\ActivityPubInboxInterface;

class EntryPinMessage implements ActivityPubInboxInterface
{
    public function __construct(public int $entryId, public bool $sticky, public ?int $actorId)
    {
    }
}

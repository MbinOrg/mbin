<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class UpdateMessage implements ActivityPubOutboxInterface
{
    public function __construct(public int $id, public string $type, public ?int $editedByUserId = null)
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\ActivityPubInboxInterface;

class LockMessage implements ActivityPubInboxInterface
{
    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}

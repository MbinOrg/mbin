<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class GenericAnnounceMessage implements ActivityPubOutboxInterface
{
    public function __construct(public int $announcingMagazineId, public array $payloadToAnnounce, public ?string $sourceInstance)
    {
    }
}

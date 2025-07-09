<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxInterface;

class GenericAnnounceMessage implements ActivityPubOutboxInterface
{
    /**
     * @param array|null $payloadToAnnounce THIS IS NOT USED ANYMORE, ONLY THERE FOR BACKWARDS COMPATIBILITY
     */
    public function __construct(public int $announcingMagazineId, public ?array $payloadToAnnounce, public ?string $sourceInstance, public ?string $innerActivityUUID, public ?string $innerActivityUrl)
    {
    }
}

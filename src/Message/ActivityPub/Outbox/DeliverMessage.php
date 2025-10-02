<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\ActivityPubOutboxDeliverInterface;

class DeliverMessage implements ActivityPubOutboxDeliverInterface
{
    public function __construct(public string $apInboxUrl, public array $payload, public bool $useOldPrivateKey = false)
    {
    }
}

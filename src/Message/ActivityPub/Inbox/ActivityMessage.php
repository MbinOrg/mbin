<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\ActivityPubInboxReceiveInterface;

/**
 * @phpstan-type RequestData array{host: string, method: string, uri: string, client_ip: string}
 */
class ActivityMessage implements ActivityPubInboxReceiveInterface
{
    /**
     * @phpstan-param RequestData|null $request
     */
    public function __construct(public string $payload, public ?array $request = null, public ?array $headers = null)
    {
    }
}

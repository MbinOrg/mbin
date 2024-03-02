<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\AsyncApMessageInterface;
use JetBrains\PhpStorm\ArrayShape;

class FlagMessage implements AsyncApMessageInterface
{
    #[ArrayShape([
        '@context' => 'mixed',
        'type' => 'string',
        'actor' => 'mixed',
        'to' => 'mixed',
        'object' => 'mixed',
        'audience' => 'string',
        'summary' => 'string',
    ])]
    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}

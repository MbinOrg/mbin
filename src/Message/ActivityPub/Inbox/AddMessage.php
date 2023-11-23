<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\AsyncApMessageInterface;

class AddMessage implements AsyncApMessageInterface
{
    public function __construct(public array $payload)
    {
    }
}

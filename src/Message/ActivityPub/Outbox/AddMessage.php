<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Outbox;

use App\Message\Contracts\AsyncApMessageInterface;

class AddMessage implements AsyncApMessageInterface
{
    public function __construct(public int $userActorId, public int $magazineId, public int $addedUserId)
    {
    }
}

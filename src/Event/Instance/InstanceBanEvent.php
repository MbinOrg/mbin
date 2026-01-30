<?php

declare(strict_types=1);

namespace App\Event\Instance;

use App\Entity\User;

class InstanceBanEvent
{
    public function __construct(public User $bannedUser, public ?User $bannedByUser, public ?string $reason)
    {
    }
}

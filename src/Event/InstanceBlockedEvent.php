<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Domain;
use App\Entity\Instance;
use App\Entity\User;

class InstanceBlockedEvent
{
    public function __construct(public Instance $instance, public User $user, public bool $blocked)
    {
    }
}

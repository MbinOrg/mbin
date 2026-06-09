<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Instance;
use App\Entity\User;

/**
 * @psalm-immutable
 */
class InstanceBlockedEvent
{
    /**
     * @param Instance $instance
     * @param User $user
     * @param bool $blocked
     * @psalm-mutation-free
     */
    public function __construct(public Instance $instance, public User $user, public bool $blocked)
    {
    }
}

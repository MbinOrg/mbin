<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Domain;
use App\Entity\Instance;
use App\Entity\User;

class InstancesGlobalBlockedEvent
{
    /**
     * @param Instance[] $instances
     */
    public function __construct(public array $instances)
    {
    }
}

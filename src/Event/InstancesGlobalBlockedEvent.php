<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Instance;

class InstancesGlobalBlockedEvent
{
    /**
     * @param Instance[] $instances
     */
    public function __construct(public array $instances)
    {
    }
}

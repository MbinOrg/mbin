<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Instance;

/**
 * @psalm-immutable
 */
class InstancesGlobalBlockedEvent
{
    /**
     * @param Instance[] $instances
     * @psalm-mutation-free
     */
    public function __construct(public array $instances)
    {
    }
}

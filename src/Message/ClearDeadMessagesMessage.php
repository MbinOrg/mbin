<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\SchedulerInterface;

class ClearDeadMessagesMessage implements SchedulerInterface
{
    public function __construct()
    {
    }
}

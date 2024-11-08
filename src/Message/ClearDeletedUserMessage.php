<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\SchedulerInterface;

class ClearDeletedUserMessage implements SchedulerInterface
{
    public function __construct()
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Scheduler\Messages\ClearDeletedUserMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class DeleteUserTaskProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(
                RecurringMessage::every('1 day', new ClearDeletedUserMessage())
            );
    }
}

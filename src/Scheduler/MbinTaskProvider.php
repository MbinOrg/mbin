<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\ClearDeadMessagesMessage;
use App\Message\ClearDeletedUserMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class MbinTaskProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        if (null === $this->schedule) {
            $this->schedule = (new Schedule())
                ->add(
                    RecurringMessage::every('1 day', new ClearDeletedUserMessage()),
                    RecurringMessage::every('1 day', new ClearDeadMessagesMessage()),
                )
                ->stateful($this->cache);
        }

        return $this->schedule;
    }
}

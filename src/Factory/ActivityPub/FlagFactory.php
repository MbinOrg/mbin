<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Activity;
use App\Entity\Report;

class FlagFactory
{
    public function __construct()
    {
    }

    public function build(Report $report): Activity
    {
        $activity = new Activity('Flag');
        $activity->setObject($report->getSubject());
        $activity->setActor($report->reporting);
        $activity->contentString = $report->reason;

        return $activity;
    }
}

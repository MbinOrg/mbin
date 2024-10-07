<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Activity;
use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;

class FlagFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(Report $report): Activity
    {
        $activity = new Activity('Flag');
        $activity->setObject($report->getSubject());
        $activity->objectUser = $report->reported;
        $activity->setActor($report->reporting);
        $activity->contentString = $report->reason;
        $activity->audience = $report->magazine;

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

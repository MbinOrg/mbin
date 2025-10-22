<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Activity;
use App\Entity\MagazineBan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BlockFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createActivityFromMagazineBan(MagazineBan $magazineBan): Activity
    {
        $activity = new Activity('Block');
        $activity->audience = $magazineBan->magazine;
        $activity->setActor($magazineBan->bannedBy);
        $activity->setObject($magazineBan);

        $this->entityManager->persist($activity);

        return $activity;
    }

    public function createActivityFromInstanceBan(User $bannedUser, User $actor): Activity
    {
        $activity = new Activity('Block');
        $activity->setActor($actor);
        $activity->setObject($bannedUser);

        $this->entityManager->persist($activity);

        return $activity;
    }
}

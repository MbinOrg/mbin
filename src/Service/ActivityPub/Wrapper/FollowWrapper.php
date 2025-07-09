<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class FollowWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(User $follower, User|Magazine $following): Activity
    {
        $activity = new Activity('Follow');
        $activity->setActor($follower);
        $activity->setObject($following);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

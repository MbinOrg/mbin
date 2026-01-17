<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class LockFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(User $actor, Entry|Post $targetObject): Activity
    {
        $activity = new Activity('Lock');
        $activity->audience = $targetObject->magazine;
        $activity->setActor($actor);
        $activity->setObject($targetObject);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

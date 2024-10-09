<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use Doctrine\ORM\EntityManagerInterface;

class LikeWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityFactory $activityFactory,
    ) {
    }

    public function build(User $user, ActivityPubActivityInterface $object): Activity
    {
        $activityObject = $this->activityFactory->create($object);
        $activity = new Activity('Like');
        $activity->setObject($activityObject['id']);
        $activity->userActor = $user;

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class FollowResponseWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(User|Magazine $actor, array $request, bool $isReject = false): Activity
    {
        $activity = new Activity($isReject ? 'Reject' : 'Accept');
        $activity->setActor($actor);
        $activity->setObject($request);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

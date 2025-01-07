<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
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

        if ($object instanceof Entry || $object instanceof EntryComment || $object instanceof Post || $object instanceof PostComment) {
            $activity->audience = $object->magazine;
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

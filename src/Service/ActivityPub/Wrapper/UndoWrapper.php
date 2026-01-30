<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UndoWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(Activity|string $object, ?User $actor = null): Activity
    {
        $activity = new Activity('Undo');
        if ($object instanceof Activity) {
            $activity->innerActivity = $object;
            $activity->setActor($object->getActor());
        } else {
            if (null === $actor) {
                throw new \LogicException('actor must not be null if the object is a url');
            }
            $activity->innerActivityUrl = $object;
            $activity->setActor($actor);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

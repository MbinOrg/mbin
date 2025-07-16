<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Magazine;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use Doctrine\ORM\EntityManagerInterface;

class AnnounceWrapper
{
    public function __construct(
        private readonly ActivityFactory $activityFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param User|Magazine                                $actor      the actor doing the announce
     * @param ActivityPubActivityInterface|Activity|string $object     the thing the actor is announcing.
     *                                                                 If it is a string it will be treated as a url to the activity this is announcing
     * @param bool                                         $idAsObject use only the id of $object as the 'object' in the payload.
     *                                                                 This should only be true for user boosts
     *
     * @return Activity an announce activity
     */
    public function build(User|Magazine $actor, ActivityPubActivityInterface|Activity|string $object, bool $idAsObject = false): Activity
    {
        $activity = new Activity('Announce');
        $activity->setActor($actor);
        if ($object instanceof Activity) {
            $activity->innerActivity = $object;
        } elseif ($object instanceof ActivityPubActivityInterface) {
            if ($idAsObject) {
                $arr = $this->activityFactory->create($object);
                $activity->setObject($arr['id']);
            } else {
                $activity->setObject($object);
            }
        } else {
            $url = filter_var($object, FILTER_VALIDATE_URL);
            if (false === $url) {
                throw new \LogicException('expecting the object to be an url if it is a string');
            }
            $activity->innerActivityUrl = $url;
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

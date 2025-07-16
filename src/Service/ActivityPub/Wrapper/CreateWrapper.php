<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use Doctrine\ORM\EntityManagerInterface;

class CreateWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function build(ActivityPubActivityInterface $item): Activity
    {
        $activity = new Activity('Create');
        $activity->setObject($item);
        if ($item instanceof Entry || $item instanceof EntryComment || $item instanceof Post || $item instanceof PostComment) {
            $activity->userActor = $item->getUser();
        } elseif ($item instanceof Message) {
            $activity->userActor = $item->sender;
        }
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

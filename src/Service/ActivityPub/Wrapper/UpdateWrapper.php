<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;

class UpdateWrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForActivity(ActivityPubActivityInterface $content, ?User $editedBy = null): Activity
    {
        $activity = new Activity('Update');
        $activity->setActor($editedBy ?? $content->getUser());
        $activity->setObject($content);

        if ($content instanceof Entry || $content instanceof EntryComment || $content instanceof Post || $content instanceof PostComment) {
            $activity->audience = $content->magazine;
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    #[ArrayShape([
        '@context' => 'mixed',
        'id' => 'mixed',
        'type' => 'string',
        'actor' => 'mixed',
        'published' => 'mixed',
        'to' => 'mixed',
        'cc' => 'mixed',
        'object' => 'array',
    ])]
    public function buildForActor(ActivityPubActorInterface $item, ?User $editedBy = null): Activity
    {
        $activity = new Activity('Update');
        $activity->setActor($editedBy ?? $item);
        $activity->setObject($item);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

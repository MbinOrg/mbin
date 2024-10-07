<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Activity;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UpdateWrapper
{
    public function __construct(
        private readonly ActivityFactory $factory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly GroupFactory $groupFactory,
        private readonly PersonFactory $personFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForActivity(ActivityPubActivityInterface $content, ?User $editedBy = null): Activity
    {
        $activity = new Activity('Update');
        $activity->setActor($editedBy ?? $content->getUser());
        $activity->setObject($content);

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

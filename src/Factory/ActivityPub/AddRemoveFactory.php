<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Activity;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AddRemoveFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildAddModerator(User $actor, User $added, Magazine $magazine): Activity
    {
        $url = null !== $magazine->apId ? $magazine->apAttributedToUrl : $this->urlGenerator->generate(
            'ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $added, $magazine, 'Add', $url);
    }

    public function buildRemoveModerator(User $actor, User $removed, Magazine $magazine): Activity
    {
        $url = null !== $magazine->apId ? $magazine->apAttributedToUrl : $this->urlGenerator->generate(
            'ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $removed, $magazine, 'Remove', $url);
    }

    public function buildAddPinnedPost(User $actor, Entry $added): Activity
    {
        $url = null !== $added->magazine->apId ? $added->magazine->apFeaturedUrl : $this->urlGenerator->generate(
            'ap_magazine_pinned', ['name' => $added->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $added, $added->magazine, 'Add', $url);
    }

    public function buildRemovePinnedPost(User $actor, Entry $removed): Activity
    {
        $url = null !== $removed->magazine->apId ? $removed->magazine->apFeaturedUrl : $this->urlGenerator->generate(
            'ap_magazine_pinned', ['name' => $removed->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $removed, $removed->magazine, 'Remove', $url);
    }

    private function build(User $actor, User|Entry $targetObject, Magazine $magazine, string $type, string $collectionUrl): Activity
    {
        $activity = new Activity($type);
        $activity->audience = $magazine;
        $activity->setActor($actor);
        $activity->setObject($targetObject);
        $activity->targetString = $collectionUrl;

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Magazine;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class UpdateWrapper
{
    public function __construct(
        private readonly ActivityFactory $factory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly GroupFactory $groupFactory,
        private readonly PersonFactory $personFactory,
    ) {
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
    public function buildForActivity(ActivityPubActivityInterface $activity, ?User $editedBy = null): array
    {
        $entity = $this->factory->create($activity, true);
        $id = Uuid::v4()->toRfc4122();

        $context = $entity['@context'];
        unset($entity['@context']);

        $entity['object']['updated'] = $activity->editedAt ? $activity->editedAt->format(DATE_ATOM) : (new \DateTime())->format(DATE_ATOM);

        $actorUrl = $entity['attributedTo'];
        if (null !== $editedBy) {
            $actorUrl = $this->urlGenerator->generate('ap_user', ['username' => $editedBy->username], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return [
            '@context' => $context,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Update',
            'actor' => $actorUrl,
            'published' => $entity['published'],
            'to' => $entity['to'],
            'cc' => $entity['cc'],
            'object' => $entity,
        ];
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
    public function buildForActor(ActivityPubActorInterface $item, ?User $editedBy = null): array
    {
        if ($item instanceof User) {
            $activity = $this->personFactory->create($item, false);
            if (null === $item->apId) {
                $cc = [$this->urlGenerator->generate('ap_user_followers', ['username' => $item->username], UrlGeneratorInterface::ABSOLUTE_URL)];
            } else {
                $cc = [$item->apFollowersUrl];
            }
        } elseif ($item instanceof Magazine) {
            $activity = $this->groupFactory->create($item, false);
            if (null === $item->apId) {
                $cc = [$this->urlGenerator->generate('ap_magazine_followers', ['name' => $item->name], UrlGeneratorInterface::ABSOLUTE_URL)];
            } else {
                $cc = [$item->apFollowersUrl];
            }
        } else {
            throw new \LogicException('Unknown actor type: '.\get_class($item));
        }
        $id = Uuid::v4()->toRfc4122();

        $actorUrl = $activity['id'];
        if (null !== $editedBy) {
            if (null === $editedBy->apId) {
                $actorUrl = $this->urlGenerator->generate('ap_user', ['username' => $editedBy->username], UrlGeneratorInterface::ABSOLUTE_URL);
            } else {
                $actorUrl = $editedBy->apProfileId;
            }
        }

        return [
            '@context' => [$this->urlGenerator->generate('ap_contexts', [], UrlGeneratorInterface::ABSOLUTE_URL)],
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Update',
            'actor' => $actorUrl,
            'published' => $activity['published'],
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => $cc,
            'object' => $activity,
        ];
    }
}

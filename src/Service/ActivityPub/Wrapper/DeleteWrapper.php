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
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\ContextsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DeleteWrapper
{
    public function __construct(
        private readonly ActivityFactory $factory,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContextsProvider $contextsProvider,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
    }

    public function build(ActivityPubActivityInterface $item, ?User $deletingUser = null): Activity
    {
        $activity = new Activity('Delete');
        $item = $this->factory->create($item);

        $userUrl = $item['attributedTo'];

        if (null !== $deletingUser) {
            // overwrite the actor in the json with the supplied deleting user
            if (null !== $deletingUser->apId) {
                $userUrl = $deletingUser->apPublicUrl;
            } else {
                $userUrl = $this->urlGenerator->generate('user_overview', ['username' => $deletingUser->username], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $activity->activityJson = json_encode([
            '@context' => $this->contextsProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $userUrl,
            'object' => [
                'id' => $item['id'],
                'type' => 'Tombstone',
            ],
            'to' => $item['to'],
            'cc' => $item['cc'],
        ]);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    public function buildForUser(User $user): Activity
    {
        $activity = new Activity('Delete');
        $this->entityManager->persist($activity);
        $this->entityManager->flush();
        $userId = $this->urlGenerator->generate('ap_user', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL);

        $activity->activityJson = json_encode([
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $activity->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Delete',
            'actor' => $userId,
            'object' => $userId,
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'cc' => [$this->urlGenerator->generate('ap_user_followers', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL)],
            // this is a lemmy specific tag, that should cause the deletion of the data of a user (see this issue https://github.com/LemmyNet/lemmy/issues/4544)
            'removeData' => true,
        ]);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    public function adjustDeletePayload(?User $actor, Entry|EntryComment|Post|PostComment $content): Activity
    {
        $payload = $this->build($content, $actor);
        $json = json_decode($payload->activityJson, true);

        if (null !== $actor && $content->user->getId() !== $actor->getId()) {
            // if the user is different, then this is a mod action. Lemmy requires a mod action to have a summary
            $json['summary'] = ' ';
        }

        if (null !== $actor?->apId) {
            $announceActivity = $this->announceWrapper->build($content->magazine, $payload);
            $json = $this->activityJsonBuilder->buildActivityJson($announceActivity);
        }

        $payload->activityJson = json_encode($json);
        $this->entityManager->persist($payload);
        $this->entityManager->flush();

        return $payload;
    }
}

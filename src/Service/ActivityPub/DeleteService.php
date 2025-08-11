<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Repository\ActivityRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use Symfony\Component\Messenger\MessageBusInterface;

class DeleteService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly ActivityRepository $activityRepository,
    ) {
    }

    public function announceIfNecessary(?User $deletingUser, Entry|EntryComment|Post|PostComment $content): void
    {
        if (null !== $deletingUser && (!$content->apId || !$content->magazine->apId || !$deletingUser->apId) && ($content->magazine->userIsModerator($deletingUser) || $content->magazine->hasSameHostAsUser($deletingUser))) {
            if ($deletingUser->apId) {
                $deleteActivity = $this->activityRepository->findFirstActivitiesByTypeAndObject('Delete', $content);
                if (!$deleteActivity) {
                    throw new \Exception('Cannot announce an activity that is not in the DB');
                }
                if (!$content->apId) {
                    // local content, but remote actor ->
                    // this activity should be just forwarded to the inbox of the accounts following the author,
                    // but we do not have anything for that, yet, so instead we just announce it as the user
                    $activity = $this->announceWrapper->build($content->user, $deleteActivity);
                } elseif (!$content->magazine->apId) {
                    // local magazine, but remote actor -> announce
                    $activity = $this->announceWrapper->build($content->magazine, $deleteActivity);
                }
            } else {
                $activity = $this->deleteWrapper->build($content, $deletingUser);
            }
            $payload = $this->activityJsonBuilder->buildActivityJson($activity);
            $this->bus->dispatch(new DeleteMessage($payload, $content->user->getId(), $content->magazine->getId()));
        }
    }
}

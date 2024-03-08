<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\ActivityPub\Outbox\AnnounceLikeMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\FavouriteManager;
use App\Service\VoteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class LikeHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly VoteManager $voteManager,
        private readonly ApActivityRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly FavouriteManager $manager,
        private readonly ApHttpClient $apHttpClient,
    ) {
    }

    public function __invoke(LikeMessage $message): void
    {
        if (!isset($message->payload['type'])) {
            return;
        }

        if ('Like' === $message->payload['type']) {
            $activity = $this->repository->findByObjectId($message->payload['object']);

            if ($activity) {
                $entity = $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
            } else {
                $object = $this->apHttpClient->getActivityObject($message->payload['object']);

                if (!empty($object)) {
                    $this->bus->dispatch(new ChainActivityMessage([$object], null, null, $message->payload));
                }

                return;
            }

            $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
            // Check if actor and entity aren't empty
            if (!empty($actor) && !empty($entity)) {
                $this->manager->toggle($actor, $entity, FavouriteManager::TYPE_LIKE);
            }
        } elseif ('Undo' === $message->payload['type']) {
            if ('Like' === $message->payload['object']['type']) {
                $activity = $this->repository->findByObjectId($message->payload['object']['object']);
                $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
                // Check if actor and activity aren't empty
                // the entity can be resolved from activity if it has values
                if (!empty($actor) && !empty($activity)) {
                    $entity = $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
                    $this->manager->toggle($actor, $entity, FavouriteManager::TYPE_UNLIKE);
                    $this->voteManager->removeVote($entity, $actor);
                }
            }
        }

        if (isset($entity) and isset($actor) and ($entity instanceof Entry or $entity instanceof EntryComment or $entity instanceof Post or $entity instanceof PostComment)) {
            if (!$entity->magazine->apId and $actor->apId and 'random' !== $entity->magazine->name) {
                // local magazine, but remote user. Don't announce for random magazine
                $this->bus->dispatch(new AnnounceLikeMessage($actor->getId(), $entity->getId(), \get_class($entity), 'Undo' === $message->payload['type']));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Contracts\VotableInterface;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\FavouriteManager;
use App\Service\VoteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DislikeHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly FavouriteManager $manager,
        private readonly ApHttpClient $apHttpClient,
        private readonly VoteManager $voteManager,
    ) {
    }

    public function __invoke(DislikeMessage $message)
    {
        if (!isset($message->payload['type'])) {
            return;
        }

        if ('Dislike' === $message->payload['type']) {
            $activity = $this->repository->findByObjectId($message->payload['object']);

            if ($activity) {
                $entity = $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
            } else {
                $object = $this->apHttpClient->getActivityObject($message->payload['object']);

                if (!empty($object)) {
                    $this->bus->dispatch(new ChainActivityMessage([$object], null, null, null, $message->payload));
                }

                return;
            }

            $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
            // Check if actor and entity aren't empty
            if (!empty($actor) && !empty($entity)) {
                if ($actor instanceof User && $entity instanceof VotableInterface) {
                    $this->voteManager->vote(VotableInterface::VOTE_DOWN, $entity, $actor);
                }
            }
        } elseif ('Undo' === $message->payload['type']) {
            if ('Dislike' === $message->payload['object']['type']) {
                $activity = $this->repository->findByObjectId($message->payload['object']['object']);
                $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
                // Check if actor and activity aren't empty
                // the entity can be resolved from activity if it has values
                if ($actor instanceof User && $activity) {
                    $entity = $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
                    if ($entity instanceof VotableInterface) {
                        $this->voteManager->removeVote($entity, $actor);
                    }
                }
            }
        }
    }
}

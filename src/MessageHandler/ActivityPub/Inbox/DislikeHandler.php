<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Contracts\VotableInterface;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Service\ActivityPubManager;
use App\Service\VoteManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DislikeHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly MessageBusInterface $bus,
        private readonly VoteManager $voteManager,
    ) {
    }

    public function __invoke(DislikeMessage $message): void
    {
        if (!isset($message->payload['type'])) {
            return;
        }

        $chainDispatchCallback = fn ($object) => $this->bus->dispatch(new ChainActivityMessage([$object], dislike: $message->payload));

        if ('Dislike' === $message->payload['type']) {
            $entity = $this->activityPubManager->getEntityObject($message->payload['object'], $message->payload, $chainDispatchCallback);
            if (!$entity) {
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
                $entity = $this->activityPubManager->getEntityObject($message->payload['object']['object'], $message->payload, $chainDispatchCallback);
                if (!$entity) {
                    return;
                }

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

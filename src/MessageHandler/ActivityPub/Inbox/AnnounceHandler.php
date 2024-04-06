<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\User;
use App\EventSubscriber\VoteHandleSubscriber;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Service\ActivityPubManager;
use App\Service\VoteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class AnnounceHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly VoteManager $manager,
        private readonly VoteHandleSubscriber $voteHandleSubscriber,
    ) {
    }

    public function __invoke(AnnounceMessage $message): void
    {
        $chainDispatchCallback = fn ($object) => $this->bus->dispatch(new ChainActivityMessage([$object], announce: $message->payload));
        if ('Announce' === $message->payload['type']) {
            $entity = $this->activityPubManager->getEntityObject($message->payload['object'], $message->payload, $chainDispatchCallback);
            if (!$entity) {
                return;
            }
            $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);

            if ($actor instanceof User) {
                $this->manager->upvote($entity, $actor);
                $this->voteHandleSubscriber->clearCache($entity);
            } else {
                $entity->lastActive = new \DateTime();
                $this->entityManager->flush();
            }
        }

        if ('Undo' === $message->payload['type']) {
            return;
        }
    }
}

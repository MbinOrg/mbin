<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\FavouriteManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class LikeHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly FavouriteManager $manager,
        private readonly ApHttpClient $apHttpClient,
    ) {
    }

    public function __invoke(LikeMessage $message): void
    {
        if (isset($message->payload['type'])) {
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
                    $entity = $this->entityManager->getRepository($activity['type'])->find((int) $activity['id']);
                    $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
                    // Check if actor and entity aren't empty
                    if (!empty($actor) && !empty($entity)) {
                        $this->manager->toggle($actor, $entity, FavouriteManager::TYPE_UNLIKE);
                    }
                }
            }
        }

        // Dead-code introduced by Ernest "Temp disable handler dispatch", in commit:
        // 4573e87f91923b9a5758e0dfacb3870d55ef1166
        //
        //        if (null === $entity->magazine->apId) {
        //            $this->bus->dispatch(
        //                new \App\Message\ActivityPub\Outbox\LikeMessage(
        //                    $actor->getId(),
        //                    $entity->getId(),
        //                    get_class($entity)
        //                )
        //            );
        //        }
    }
}

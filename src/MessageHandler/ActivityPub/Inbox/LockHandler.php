<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\Post;
use App\Message\ActivityPub\Inbox\LockMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryManager;
use App\Service\PostManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class LockHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ActivityPubManager $activityPubManager,
        private readonly KernelInterface $kernel,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryManager $entryManager,
        private readonly PostManager $postManager,
        private readonly ActivityRepository $activityRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(LockMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof LockMessage)) {
            throw new \LogicException();
        }
        $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);

        if (null === $actor) {
            $this->logger->warning('[LockHandler::doWork] Could not find an actor for activity {id}. Supplied actor: "{actor}"', ['id' => $message->payload['id'], 'actor' => $message->payload['actor']]);

            return;
        }
        $isUndo = 'Undo' === $message->payload['type'];
        $payload = $message->payload;

        if ($isUndo) {
            $payload = $message->payload['object'];
        }
        $objectId = \is_array($payload['object']) ? $payload['object']['id'] : $payload['object'];
        $object = $this->apActivityRepository->findByObjectId($objectId);
        if (null === $object) {
            $this->logger->warning('[LockHandler::doWork] Could not find an object for activity "{id}". Supplied object: "{object}".', ['id' => $payload['id'], 'object' => $message->payload['object']]);

            return;
        }
        $objectEntity = $this->entityManager->getRepository($object['type'])->find($object['id']);
        if ($objectEntity instanceof Entry || $objectEntity instanceof Post) {
            if ($objectEntity->magazine->userIsModerator($actor) || $actor->getId() === $objectEntity->user->getId() || $actor->apDomain === $objectEntity->user->apDomain || $actor->apDomain === $objectEntity->magazine->apDomain) {
                // actor is magazine moderator or author or from the same instance as the author (so probably an instance admin)
                // or from the same instance as the magazine (so probably an instance admin of the magazine)
                if ($isUndo && $objectEntity->isLocked || !$isUndo && !$objectEntity->isLocked) {
                    // if it is an undo it should not be locked and if it is not an undo it should be locked,
                    // so under these 2 conditions we need to toggle the state
                    if ($objectEntity instanceof Entry) {
                        $this->entryManager->toggleLock($objectEntity, $actor);
                    } else {
                        $this->postManager->toggleLock($objectEntity, $actor);
                    }
                }
                if (null === $objectEntity->magazine->apId && 'random' !== $objectEntity->magazine->name) {
                    $lockActivity = $this->activityRepository->createForRemoteActivity($message->payload, $objectEntity);
                    $lockActivity->setActor($actor);
                    $this->bus->dispatch(new GenericAnnounceMessage($objectEntity->magazine->getId(), null, $actor->apInboxUrl, $lockActivity->uuid->toString(), null));
                }
            }
        } else {
            $this->logger->warning('[LockHandler::doWork] entity was not entry or post, but "{type}"', ['type' => \get_class($objectEntity)]);
        }
    }
}

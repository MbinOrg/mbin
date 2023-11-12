<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Message\ActivityPub\Inbox\RemoveMessage;
use App\Repository\MagazineRepository;
use App\Service\ActivityPubManager;
use App\Service\MagazineManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RemoveHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly MagazineManager $magazineManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemoveMessage $message): void
    {
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($message['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($message['target']);
        if (!$targetMag) {
            throw new \LogicException("could not find a magazine with moderators url like: '{$message['target']}'");
        }
        if (!$targetMag->userIsModerator($actor) and !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not remove moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($message['object']);
        $objectMod = $targetMag->getUserAsModeratorOrNull($object);

        if (null === $objectMod) {
            $this->logger->warning("the user '$object->username' ({$object->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()} and can therefore not be removed as one. Discarding message");
        }
        $this->logger->info("'$actor->username' ({$actor->getId()}) removed '$object->username' ({$object->getId()}) as moderator from '$targetMag->name' ({$targetMag->getId()}");
        if ($targetMag->apId) {
            // don't federate the added moderator if the magazine is not local
            $targetMag->removeUserAsModerator($object);
        } else {
            // use the manager to trigger outbox federation for the new moderator
            // use case for this: a remote moderator added a new moderator
            $this->magazineManager->removeModerator($objectMod);
        }
    }
}

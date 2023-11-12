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
        $payload = $message->payload;
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($payload['target']);
        if (!$targetMag) {
            throw new \LogicException("could not find a magazine with moderators url like: '{$payload['target']}'");
        }
        if (!$targetMag->userIsModerator($actor) and !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not remove moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['object']);
        $objectMod = $targetMag->getUserAsModeratorOrNull($object);

        if (null === $objectMod) {
            $this->logger->warning("the user '$object->username' ({$object->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and can therefore not be removed as one. Discarding message");
        }
        $this->logger->info("'$actor->username' ({$actor->getId()}) removed '$object->username' ({$object->getId()}) as moderator from '$targetMag->name' ({$targetMag->getId()})");
        $this->magazineManager->removeModerator($objectMod, $actor);
    }
}

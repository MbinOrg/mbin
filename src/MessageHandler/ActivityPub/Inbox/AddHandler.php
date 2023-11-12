<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
use App\Entity\Moderator;
use App\Message\ActivityPub\Inbox\AddMessage;
use App\Repository\MagazineRepository;
use App\Service\ActivityPubManager;
use App\Service\MagazineManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AddHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly MagazineManager $magazineManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AddMessage $message): void
    {
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($message['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($message['target']);
        if (!$targetMag) {
            throw new \LogicException("could not find a magazine with moderators url like: '{$message['target']}'");
        }
        if (!$targetMag->userIsModerator($actor) and !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not add moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($message['object']);

        if ($targetMag->userIsModerator($object)) {
            $this->logger->warning("the user '$object->username' ({$object->getId()}) already is a moderator of $targetMag->name ({$targetMag->getId()}. Discarding message");
        }
        $this->logger->info("'$actor->username' ({$actor->getId()}) added '$object->username' ({$object->getId()}) as moderator to '$targetMag->name' ({$targetMag->getId()}");
        if ($targetMag->apId) {
            // don't federate the added moderator if the magazine is not local
            $targetMag->addModerator(new Moderator($targetMag, $object, $actor, false, true));
        } else {
            // use the manager to trigger outbox federation for the new moderator
            // use case for this: a remote moderator added a new moderator
            $this->magazineManager->addModerator(new ModeratorDto($targetMag, $object, $actor));
        }
    }
}

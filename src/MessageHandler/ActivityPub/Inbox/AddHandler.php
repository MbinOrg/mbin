<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
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
        $payload = $message->payload;
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($payload['target']);
        if (!$targetMag) {
            throw new \LogicException("could not find a magazine with moderators url like: '{$payload['target']}'");
        }
        if (!$targetMag->userIsModerator($actor) and !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not add moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['object']);

        if ($targetMag->userIsModerator($object)) {
            $this->logger->warning("the user '$object->username' ({$object->getId()}) already is a moderator of $targetMag->name ({$targetMag->getId()}). Discarding message");

            return;
        }
        $this->logger->info("'$actor->username' ({$actor->getId()}) added '$object->username' ({$object->getId()}) as moderator to '$targetMag->name' ({$targetMag->getId()})");
        $this->magazineManager->addModerator(new ModeratorDto($targetMag, $object, $actor));
    }
}

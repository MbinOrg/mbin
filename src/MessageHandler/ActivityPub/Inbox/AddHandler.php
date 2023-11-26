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
            $this->logger->warning('the user "{added}" ({addedId}) already is a moderator of "{magName}" ({magId}). Discarding message', [
                'added' => $object->username,
                'addedId' => $object->getId(),
                'magName' => $targetMag->name,
                'magId' => $targetMag->getId(),
            ]);

            return;
        }
        $this->logger->info('"{actor}" ({actorId}) added "{added}" ({addedId}) as moderator to "{magName}" ({magId})', [
            'actor' => $actor->username,
            'actorId' => $actor->getId(),
            'added' => $object->username,
            'addedId' => $object->getId(),
            'magName' => $targetMag->name,
            'magId' => $targetMag->getId(),
        ]);
        $this->magazineManager->addModerator(new ModeratorDto($targetMag, $object, $actor));
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\RemoveMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\ApActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryManager;
use App\Service\MagazineManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class RemoveHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly MagazineManager $magazineManager,
        private readonly LoggerInterface $logger,
        private readonly EntryRepository $entryRepository,
        private readonly EntryManager $entryManager,
        private readonly SettingsManager $settingsManager,
        private readonly MessageBusInterface $bus,
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(RemoveMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof RemoveMessage)) {
            throw new \LogicException();
        }
        $payload = $message->payload;
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($payload['target']);
        if ($targetMag) {
            $this->handleModeratorRemove($payload['object'], $targetMag, $actor, $payload);

            return;
        }
        $targetMag = $this->magazineRepository->getMagazineFromPinnedUrl($payload['target']);
        if ($targetMag) {
            $this->handlePinnedRemove($payload['object'], $targetMag, $actor, $payload);

            return;
        }
        throw new \LogicException("could not find a magazine with moderators url like: '{$payload['target']}'");
    }

    public function handleModeratorRemove($object1, Magazine $targetMag, Magazine|User $actor, array $messagePayload): void
    {
        if (!$targetMag->userIsModerator($actor) && !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not remove moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($object1);
        $objectMod = $targetMag->getUserAsModeratorOrNull($object);

        $loggerParams = [
            'toRemove' => $object->username,
            'toRemoveId' => $object->getId(),
            'magName' => $targetMag->name,
            'magId' => $targetMag->getId(),
        ];

        if (null === $objectMod) {
            $this->logger->warning('the user "{toRemove}" ({toRemoveId}) is not a moderator of {magName} ({magId}) and can therefore not be removed as one. Discarding message', $loggerParams);

            return;
        } elseif ($objectMod->isOwner) {
            $this->logger->warning('the user "{toRemove}" ({toRemoveId}) is the owner of {magName} ({magId}) and can therefore not be removed. Discarding message', $loggerParams);

            return;
        }

        $this->logger->info('[RemoveHandler::handleModeratorRemove] "{actor}" ({actorId}) removed "{removed}" ({removedId}) as moderator from "{magName}" ({magId})', [
            'actor' => $actor->username,
            'actorId' => $actor->getId(),
            'removed' => $object->username,
            'removedId' => $object->getId(),
            'magName' => $targetMag->name,
            'magId' => $targetMag->getId(),
        ]);
        $this->magazineManager->removeModerator($objectMod, $actor);

        if (null === $targetMag->apId) {
            $activityToAnnounce = $messagePayload;
            unset($activityToAnnounce['@context']);
            $activity = $this->activityRepository->createForRemoteActivity($activityToAnnounce);
            $this->bus->dispatch(new GenericAnnounceMessage($targetMag->getId(), null, parse_url($actor->apDomain, PHP_URL_HOST), $activity->uuid->toString(), null));
        }
    }

    private function handlePinnedRemove(mixed $object, Magazine $targetMag, User $actor, array $messagePayload): void
    {
        if (!$targetMag->userIsModerator($actor) && !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. They can therefore not add pinned entries");
        }

        $apId = null;
        if (\is_string($object)) {
            $apId = $object;
        } elseif (\is_array($object)) {
            $apId = $object['id'];
        } else {
            throw new \LogicException('the added object is neither a string or an array');
        }
        if ($this->settingsManager->isLocalUrl($apId)) {
            $pair = $this->apActivityRepository->findLocalByApId($apId);
            if (Entry::class === $pair['type']) {
                $existingEntry = $this->entryRepository->findOneBy(['id' => $pair['id']]);
                if ($existingEntry && $existingEntry->sticky) {
                    $this->logger->info('[RemoveHandler::handlePinnedRemove] Unpinning entry {e} to magazine {m}', ['e' => $existingEntry->title, 'm' => $existingEntry->magazine->name]);
                    $this->entryManager->pin($existingEntry, $actor);
                    if (null === $targetMag->apId) {
                        $this->announceRemovePin($actor, $targetMag, $messagePayload);
                    }
                }
            }
        } else {
            $existingEntry = $this->entryRepository->findOneBy(['apId' => $apId]);
            if ($existingEntry && $existingEntry->sticky) {
                $this->logger->info('[RemoveHandler::handlePinnedRemove] Unpinning entry {e} to magazine {m}', ['e' => $existingEntry->title, 'm' => $existingEntry->magazine->name]);
                $this->entryManager->pin($existingEntry, $actor);
                if (null === $targetMag->apId) {
                    $this->announceRemovePin($actor, $targetMag, $messagePayload);
                }
            }
        }
    }

    private function announceRemovePin(User $actor, Magazine $targetMag, array $object): void
    {
        $activity = $this->activityRepository->createForRemoteActivity($object);
        $this->bus->dispatch(new GenericAnnounceMessage($targetMag->getId(), null, parse_url($actor->apDomain, PHP_URL_HOST), $activity->uuid->toString(), null));
    }
}

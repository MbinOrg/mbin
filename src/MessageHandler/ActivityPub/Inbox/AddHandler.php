<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\AddMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Repository\ApActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\EntryManager;
use App\Service\MagazineManager;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class AddHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClient $apHttpClient,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly MagazineManager $magazineManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
        private readonly EntryRepository $entryRepository,
        private readonly EntryManager $entryManager,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function __invoke(AddMessage $message): void
    {
        $payload = $message->payload;
        $actor = $this->activityPubManager->findUserActorOrCreateOrThrow($payload['actor']);
        $targetMag = $this->magazineRepository->getMagazineFromModeratorsUrl($payload['target']);
        if ($targetMag) {
            $this->handleModeratorAdd($targetMag, $actor, $payload['object']);

            return;
        }
        $targetMag = $this->magazineRepository->getMagazineFromPinnedUrl($payload['target']);
        if ($targetMag) {
            $this->handlePinnedAdd($targetMag, $actor, $payload['object']);

            return;
        }
        throw new \LogicException("could not find a magazine with moderators url like: '{$payload['target']}'");
    }

    public function handleModeratorAdd(Magazine $targetMag, Magazine|User $actor, $object1): void
    {
        if (!$targetMag->userIsModerator($actor) and !$targetMag->hasSameHostAsUser($actor)) {
            throw new \LogicException("the user '$actor->username' ({$actor->getId()}) is not a moderator of $targetMag->name ({$targetMag->getId()}) and is not from the same instance. He can therefore not add moderators");
        }

        $object = $this->activityPubManager->findUserActorOrCreateOrThrow($object1);

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

    private function handlePinnedAdd(Magazine $targetMag, User $actor, mixed $object): void
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
                if ($existingEntry && !$existingEntry->sticky) {
                    $this->logger->info('pinning entry {e} to magazine {m}', ['e' => $existingEntry->title, 'm' => $existingEntry->magazine->name]);
                    $this->entryManager->pin($existingEntry, $actor);
                }
            }
        } else {
            $existingEntry = $this->entryRepository->findOneBy(['apId' => $apId]);
            if ($existingEntry) {
                if (null !== $existingEntry->magazine->apFeaturedUrl) {
                    $this->apHttpClient->invalidateCollectionObjectCache($existingEntry->magazine->apFeaturedUrl);
                }
                if (!$existingEntry->sticky) {
                    $this->logger->info('pinning entry {e} to magazine {m}', ['e' => $existingEntry->title, 'm' => $existingEntry->magazine->name]);
                    $this->entryManager->pin($existingEntry, $actor);
                }
            } else {
                if (!\is_array($object)) {
                    $object = $this->apHttpClient->getActivityObject($apId);
                }
                $this->bus->dispatch(new CreateMessage($object, true));
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\ActivityPub\Outbox\EntryPinMessage;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class EntryPinMessageHandler
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly EntryRepository $entryRepository,
        private readonly UserRepository $userRepository,
        private readonly AddRemoveFactory $addRemoveFactory,
        private readonly MagazineRepository $magazineRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(EntryPinMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $entry = $this->entryRepository->findOneBy(['id' => $message->entryId]);
        $user = $this->userRepository->findOneBy(['id' => $message->actorId]);
        if ($message->sticky) {
            $activity = $this->addRemoveFactory->buildAddPinnedPost($user, $entry);
        } else {
            $activity = $this->addRemoveFactory->buildRemovePinnedPost($user, $entry);
        }

        if ($entry->magazine->apId) {
            $audience = [$entry->magazine->apInboxUrl];
        } else {
            $audience = $this->magazineRepository->findAudience($entry->magazine);
        }

        foreach ($audience as $inboxUrl) {
            if (!$this->settingsManager->isBannedInstance($inboxUrl)) {
                $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
            }
        }
    }
}

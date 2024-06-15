<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\ActivityPub\Outbox\RemoveMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class RemoveHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settingsManager,
        private readonly MessageBusInterface $bus,
        private readonly AddRemoveFactory $factory,
    ) {
    }

    public function __invoke(RemoveMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $actor = $this->userRepository->find($message->userActorId);
        $removed = $this->userRepository->find($message->removedUserId);
        $magazine = $this->magazineRepository->find($message->magazineId);
        if ($magazine->apId) {
            $audience = [$magazine->apInboxUrl];
        } else {
            $audience = $this->magazineRepository->findAudience($magazine);
        }

        $activity = $this->factory->buildRemoveModerator($actor, $removed, $magazine);
        foreach ($audience as $inboxUrl) {
            if (!$this->settingsManager->isBannedInstance($inboxUrl)) {
                $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
            }
        }
    }
}

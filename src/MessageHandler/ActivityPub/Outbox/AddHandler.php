<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\AddMessage;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class AddHandler
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

    public function __invoke(AddMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $actor = $this->userRepository->find($message->userActorId);
        $added = $this->userRepository->find($message->addedUserId);
        $magazine = $this->magazineRepository->find($message->magazineId);
        if ($magazine->apId) {
            $audience = [$magazine->apInboxUrl];
        } else {
            $audience = $this->magazineRepository->findAudience($magazine);
        }

        $activity = $this->factory->buildAdd($actor, $added, $magazine);
        foreach ($audience as $inboxUrl) {
            if (!$this->settingsManager->isBannedInstance($inboxUrl)) {
                $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
            }
        }
    }
}

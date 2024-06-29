<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\AddMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AddHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly SettingsManager $settingsManager,
        private readonly AddRemoveFactory $factory,
        private readonly DeliverManager $deliverManager,
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
        $this->deliverManager->deliver($audience, $activity);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
    ) {
    }

    public function __invoke(DeleteMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(DeleteMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $user = $this->userRepository->find($message->userId);
        $magazine = $this->magazineRepository->find($message->magazineId);

        $inboxes = array_filter(array_unique(array_merge(
            $this->userRepository->findAudience($user),
            $this->activityPubManager->createInboxesFromCC($message->payload, $user),
            $this->magazineRepository->findAudience($magazine)
        )));
        $this->deliverManager->deliver($inboxes, $message->payload);
    }
}

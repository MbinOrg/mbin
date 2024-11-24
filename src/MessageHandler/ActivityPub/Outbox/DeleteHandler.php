<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(DeleteMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof DeleteMessage)) {
            throw new \LogicException();
        }

        $user = $this->userRepository->find($message->userId);
        $magazine = $this->magazineRepository->find($message->magazineId);

        $inboxes = array_filter(array_unique(array_merge(
            $this->userRepository->findAudience($user),
            $this->activityPubManager->createInboxesFromCC($message->payload, $user),
        )));

        if ('random' !== $magazine->name) {
            // only add the magazine subscribers if it is not the random magazine
            $inboxes = array_filter(array_unique(array_merge(
                $inboxes,
                $this->magazineRepository->findAudience($magazine),
            )));
        }

        $this->deliverManager->deliver($inboxes, $message->payload);
    }
}

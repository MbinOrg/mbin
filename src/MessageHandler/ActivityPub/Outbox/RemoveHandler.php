<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\ActivityPub\Outbox\RemoveMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class RemoveHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
        private readonly SettingsManager $settingsManager,
        private readonly MessageBusInterface $bus,
        private readonly AddRemoveFactory $factory,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(RemoveMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof RemoveMessage)) {
            throw new \LogicException();
        }

        $actor = $this->userRepository->find($message->userActorId);
        $removed = $this->userRepository->find($message->removedUserId);
        $magazine = $this->magazineRepository->find($message->magazineId);
        if ($magazine->apId) {
            $audience = [$magazine->apInboxUrl];
        } else {
            $audience = $this->magazineRepository->findAudience($magazine);
        }

        $activity = $this->factory->buildRemove($actor, $removed, $magazine);
        foreach ($audience as $inboxUrl) {
            if (!$this->settingsManager->isBannedInstance($inboxUrl)) {
                $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
            }
        }
    }
}

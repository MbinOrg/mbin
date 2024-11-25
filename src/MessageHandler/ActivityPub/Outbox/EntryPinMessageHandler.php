<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\EntryPinMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EntryPinMessageHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly EntryRepository $entryRepository,
        private readonly UserRepository $userRepository,
        private readonly AddRemoveFactory $addRemoveFactory,
        private readonly MagazineRepository $magazineRepository,
        private readonly DeliverManager $deliverManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(EntryPinMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof EntryPinMessage)) {
            throw new \LogicException();
        }
        $entry = $this->entryRepository->findOneBy(['id' => $message->entryId]);
        $user = $this->userRepository->findOneBy(['id' => $message->actorId]);

        if (null !== $entry->magazine->apId && null !== $user->apId) {
            $this->logger->warning('got an EntryPinMessage for remote magazine {m} by remote user {u}. That does not need to be propagated, as this instance is not the source', ['m' => $entry->magazine->apId, 'u' => $user->apId]);

            return;
        }

        if ('random' === $entry->magazine->name) {
            // do not federate the random magazine
            return;
        }

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

        $this->deliverManager->deliver($audience, $activity);
    }
}

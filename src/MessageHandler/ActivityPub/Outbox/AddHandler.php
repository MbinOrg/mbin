<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\AddMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AddHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly SettingsManager $settingsManager,
        private readonly AddRemoveFactory $factory,
        private readonly DeliverManager $deliverManager,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(AddMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof AddMessage)) {
            throw new \LogicException();
        }

        $actor = $this->userRepository->find($message->userActorId);
        $added = $this->userRepository->find($message->addedUserId);
        $magazine = $this->magazineRepository->find($message->magazineId);
        if ($magazine->apId) {
            $audience = [$magazine->apInboxUrl];
        } else {
            if ('random' === $magazine->name) {
                // do not federate the random magazine
                return;
            }
            $audience = $this->magazineRepository->findAudience($magazine);
        }

        $activity = $this->factory->buildAddModerator($actor, $added, $magazine);
        $this->deliverManager->deliver($audience, $activity);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Message;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPubManager;
use App\Service\MessageManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CreateHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly CreateWrapper $createWrapper,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly MessageManager $messageManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $entity = $this->entityManager->getRepository($message->type)->find($message->id);

        $activity = $this->createWrapper->build($entity);

        if ($entity instanceof Message) {
            $receivers = $this->messageManager->findAudience($entity->thread);
            $this->logger->info('sending message to {p}', ['p' => $receivers]);
        } else {
            $receivers = [
                ...$this->userRepository->findAudience($entity->user),
                ...$this->activityPubManager->createInboxesFromCC($activity, $entity->user),
                ...$this->magazineRepository->findAudience($entity->magazine),
            ];
        }

        $this->deliver(array_filter($receivers), $activity);
    }

    private function deliver(array $followers, array $activity): void
    {
        foreach ($followers as $follower) {
            if (!$follower) {
                continue;
            }

            $inboxUrl = \is_string($follower) ? $follower : $follower->apInboxUrl;

            if ($this->settingsManager->isBannedInstance($inboxUrl)) {
                continue;
            }

            $this->bus->dispatch(new DeliverMessage($follower, $activity));
        }
    }
}

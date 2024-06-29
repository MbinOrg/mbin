<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Message;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPubManager;
use App\Service\MessageManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly CreateWrapper $createWrapper,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly MessageManager $messageManager,
        private readonly LoggerInterface $logger,
        private readonly DeliverManager $deliverManager,
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
        $this->deliverManager->deliver(array_filter(array_unique($receivers)), $activity);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Message;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\MessageManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateHandler extends MbinMessageHandler
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
        parent::__construct($this->entityManager);
    }

    public function __invoke(CreateMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof CreateMessage)) {
            throw new \LogicException();
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
            if ('random' === $entity->magazine->name) {
                // do not federate the random magazine
                return;
            }
            $this->logger->debug('sending create activity to {p}', ['p' => $receivers]);
        }
        $this->deliverManager->deliver(array_filter(array_unique($receivers)), $activity);
    }
}

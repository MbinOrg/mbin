<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApHttpClient $client,
        private readonly ActivityPubManager $manager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliverMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(DeliverMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        if ('Announce' !== $message->payload['type']) {
            $url = $message->payload['object']['attributedTo'] ?? $message->payload['actor'];
        } else {
            $url = $message->payload['actor'];
        }
        $this->logger->debug("Getting Actor for url: $url");
        $actor = $this->manager->findActorOrCreate($url);

        if (!$actor) {
            $this->logger->debug('got no actor :(');

            return;
        }

        if ($actor instanceof User && $actor->isBanned) {
            $this->logger->debug('got an actor, but he is banned :(');

            return;
        }

        $this->client->post($message->apInboxUrl, $actor, $message->payload);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApHttpClient $client,
        private readonly ActivityPubManager $manager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(DeliverMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof DeliverMessage)) {
            throw new \LogicException();
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

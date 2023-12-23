<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeliverHandler
{
    public function __construct(
        private readonly ApHttpClient $client,
        private readonly ActivityPubManager $manager,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function __invoke(DeliverMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        if ('Announce' !== $message->payload['type']) {
            $actor = $this->manager->findActorOrCreate(
                $message->payload['object']['attributedTo'] ?? $message->payload['actor']
            );
        } else {
            $actor = $this->manager->findActorOrCreate($message->payload['actor']);
        }

        if (!$actor) {
            return;
        }

        if ($actor instanceof User && $actor->isBanned) {
            return;
        }

        $this->client->post($message->apInboxUrl, $actor, $message->payload);
    }
}

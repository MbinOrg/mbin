<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Traits\ActivityPubActorTrait;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class DeliverManager
{
    public function __construct(
        private SettingsManager $settingsManager,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param string[]|ActivityPubActorTrait[] $inboxes
     */
    public function deliver(array $inboxes, array $activity): void
    {
        foreach ($inboxes as $inbox) {
            if (!$inbox) {
                continue;
            }

            $inboxUrl = \is_string($inbox) ? $inbox : $inbox->apInboxUrl;

            if ($this->settingsManager->isBannedInstance($inboxUrl)) {
                continue;
            }

            $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
        }
    }
}

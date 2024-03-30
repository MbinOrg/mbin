<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\ActivityPub\Outbox\DeliverMessage;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class DeliverManager
{
    public function __construct(
        private SettingsManager $settingsManager,
        private MessageBusInterface $bus,
    ) {
    }

    public function deliver(array $followers, array $activity): void
    {
        foreach ($followers as $follower) {
            if (!$follower) {
                continue;
            }

            $inboxUrl = \is_string($follower) ? $follower : $follower->apInboxUrl;

            if ($this->settingsManager->isBannedInstance($inboxUrl)) {
                continue;
            }

            $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity));
        }
    }
}

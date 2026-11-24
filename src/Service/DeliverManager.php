<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Traits\ActivityPubActorTrait;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Utils\LogContext;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class DeliverManager
{
    public function __construct(
        private SettingsManager $settingsManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string[]|ActivityPubActorTrait[] $inboxes
     */
    public function deliver(array $inboxes, array $activity, bool $useOldPrivateKey = false): void
    {
        $skipped = 0;
        foreach ($inboxes as $inbox) {
            if (!$inbox) {
                continue;
            }

            $inboxUrl = \is_string($inbox) ? $inbox : $inbox->apInboxUrl;

            if ($this->settingsManager->isBannedInstance($inboxUrl)) {
                continue;
            }

            if ($this->settingsManager->isLocalUrl($inboxUrl)) {
                ++$skipped;
                continue;
            }

            $this->bus->dispatch(new DeliverMessage($inboxUrl, $activity, $useOldPrivateKey));
        }

        if ($skipped > 0) {
            $this->logger->warning('[DeliverManager::deliver] Skipped {skipped} local inboxes during federation delivery', [
                'activity_type' => LogContext::type($activity['type'] ?? null),
                'activity_id' => LogContext::identifier($activity['id'] ?? null),
                'skipped' => $skipped,
            ]);
        }
    }
}

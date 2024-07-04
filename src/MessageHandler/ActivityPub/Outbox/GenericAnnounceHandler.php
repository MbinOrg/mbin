<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Repository\MagazineRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
readonly class GenericAnnounceHandler
{
    public function __construct(
        private SettingsManager $settingsManager,
        private UrlGeneratorInterface $urlGenerator,
        private MagazineRepository $magazineRepository,
        private AnnounceWrapper $announceWrapper,
        private DeliverManager $deliverManager,
    ) {
    }

    public function __invoke(GenericAnnounceMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $magazine = $this->magazineRepository->find($message->announcingMagazineId);
        if (null !== $magazine->apId) {
            return;
        }
        $magazineUrl = $this->urlGenerator->generate('ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL);
        $announce = $this->announceWrapper->build($magazineUrl, $message->payloadToAnnounce);
        $inboxes = $this->magazineRepository->findAudience($magazine);
        $this->deliverManager->deliver($inboxes, $announce);
    }
}

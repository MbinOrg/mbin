<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class GenericAnnounceHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MagazineRepository $magazineRepository,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly DeliverManager $deliverManager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(GenericAnnounceMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof GenericAnnounceMessage)) {
            throw new \LogicException();
        }
        $magazine = $this->magazineRepository->find($message->announcingMagazineId);
        if (null !== $magazine->apId) {
            return;
        }

        if ('random' === $magazine->name) {
            // do not federate the random magazine
            return;
        }
        $magazineUrl = $this->urlGenerator->generate('ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL);
        $announce = $this->announceWrapper->build($magazineUrl, $message->payloadToAnnounce);
        $inboxes = array_filter($this->magazineRepository->findAudience($magazine), fn ($item) => null !== $item && $item !== $message->sourceInstance);
        $this->deliverManager->deliver($inboxes, $announce);
    }
}

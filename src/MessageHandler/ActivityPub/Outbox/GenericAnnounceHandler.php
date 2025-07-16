<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\MagazineRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class GenericAnnounceHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MagazineRepository $magazineRepository,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly DeliverManager $deliverManager,
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
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

        if (null !== $message->innerActivityUUID) {
            $object = $this->activityRepository->findOneBy(['uuid' => Uuid::fromString($message->innerActivityUUID)]);
        } elseif (null !== $message->innerActivityUrl) {
            $object = $message->innerActivityUrl;
        } else {
            throw new \LogicException('expect at least one of innerActivityUUID or innerActivityUrl to not be null');
        }

        $announce = $this->announceWrapper->build($magazine, $object);
        $json = $this->activityJsonBuilder->buildActivityJson($announce);
        $inboxes = array_filter($this->magazineRepository->findAudience($magazine), fn ($item) => null !== $item && $item !== $message->sourceInstance);
        $this->deliverManager->deliver($inboxes, $json);
    }
}

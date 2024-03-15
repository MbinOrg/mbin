<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\ActivityPub\ActivityFactory;
use App\Message\ActivityPub\Outbox\AnnounceMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class AnnounceHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly CreateWrapper $createWrapper,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ActivityFactory $activityFactory,
        private readonly DeliverManager $deliverManager,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function __invoke(AnnounceMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        if (null !== $message->userId) {
            $actor = $this->userRepository->find($message->userId);
        } elseif (null !== $message->magazineId) {
            $actor = $this->magazineRepository->find($message->magazineId);
        } else {
            throw new UnrecoverableMessageHandlingException('no actor was specified');
        }

        $object = $this->entityManager->getRepository($message->objectType)->find($message->objectId);

        $activity = $this->announceWrapper->build(
            $this->activityPubManager->getActorProfileId($actor),
            $this->activityFactory->create($object),
            true
        );

        if ($actor instanceof Magazine && ($object instanceof Entry || $object instanceof Post || $object instanceof EntryComment || $object instanceof PostComment)) {
            $wrapperObject = $this->createWrapper->build($object);
            unset($wrapperObject['@context']);
            $activity['object'] = $wrapperObject;
        }

        if ($message->removeAnnounce) {
            $activity = $this->undoWrapper->build($activity);
        }

        $inboxes = array_merge(
            $this->magazineRepository->findAudience($object->magazine),
            [$object->user->apInboxUrl, $object->magazine->apId ? $object->magazine->apInboxUrl : null]
        );

        if ($actor instanceof User) {
            $inboxes = array_merge(
                $inboxes,
                $this->userRepository->findAudience($actor),
                $this->activityPubManager->createInboxesFromCC($activity, $actor),
            );
        } elseif ($actor instanceof Magazine) {
            $createHost = parse_url($object->apId, PHP_URL_HOST);
            $inboxes = array_filter(array_merge(
                $inboxes,
                $this->magazineRepository->findAudience($actor),
            ), fn ($item) => null !== $item and $createHost !== parse_url($item, PHP_URL_HOST));
        }

        $inboxes = array_filter(array_unique($inboxes));
        $this->deliverManager->deliver($inboxes, $activity);
    }
}

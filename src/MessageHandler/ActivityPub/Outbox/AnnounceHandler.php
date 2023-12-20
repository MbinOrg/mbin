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
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly MessageBusInterface $bus,
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
        );

        if ($object instanceof Entry or $object instanceof Post or $object instanceof EntryComment or $object instanceof PostComment) {
            $wrapperObject = $this->createWrapper->build($object);
            unset($wrapperObject['@context']);
            $activity['object'] = $wrapperObject;
        }

        if ($message->removeAnnounce) {
            $activity = $this->undoWrapper->build($activity);
        }

        if ($actor instanceof User) {
            $this->deliver(array_filter($this->userRepository->findAudience($actor)), $activity);
            $this->deliver(array_filter($this->activityPubManager->createInboxesFromCC($activity, $actor)), $activity);
            $this->deliver(array_filter($this->magazineRepository->findAudience($object->magazine)), $activity);
            $this->deliver([$object->user->apInboxUrl], $activity);
        } elseif ($actor instanceof Magazine) {
            $createHost = parse_url($object->apId, PHP_URL_HOST);
            $this->deliver(array_filter($this->magazineRepository->findAudience($actor), fn ($item) => null !== $item and $createHost !== parse_url($item, PHP_URL_HOST)), $activity);
        }
    }

    private function deliver(array $followers, array $activity): void
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

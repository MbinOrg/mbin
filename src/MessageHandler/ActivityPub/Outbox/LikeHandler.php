<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\ActivityPub\ActivityFactory;
use App\Message\ActivityPub\Outbox\LikeMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\LikeWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LikeHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LikeWrapper $likeWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ActivityFactory $activityFactory,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
    ) {
    }

    public function __invoke(LikeMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(LikeMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $user = $this->userRepository->find($message->userId);
        /** @var Entry|EntryComment|Post|PostComment $object */
        $object = $this->entityManager->getRepository($message->objectType)->find($message->objectId);

        $activity = $this->likeWrapper->build(
            $this->activityPubManager->getActorProfileId($user),
            $this->activityFactory->create($object),
        );

        if ($message->removeLike) {
            $activity = $this->undoWrapper->build($activity);
        }

        $inboxes = array_filter(array_unique(array_merge(
            $this->userRepository->findAudience($user),
            $this->magazineRepository->findAudience($object->magazine),
            [$object->user->apInboxUrl, $object->magazine->apId ? $object->magazine->apInboxUrl : null]
        )));
        $this->deliverManager->deliver($inboxes, $activity);
    }
}

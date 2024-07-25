<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\ActivityPub\ActivityFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Message\ActivityPub\Outbox\AnnounceLikeMessage;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\LikeWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class AnnounceLikeHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly LikeWrapper $likeWrapper,
        private readonly ActivityFactory $activityFactory,
        private readonly SettingsManager $settingsManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PersonFactory $personFactory,
        private readonly DeliverManager $deliverManager,
    ) {
    }

    public function __invoke(AnnounceLikeMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(AnnounceLikeMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }

        $user = $this->userRepository->find($message->userId);
        /** @var Entry|EntryComment|Post|PostComment $object */
        $object = $this->entityManager->getRepository($message->objectType)->find($message->objectId);

        // blacklist remote magazines
        if (null !== $object->magazine->apId) {
            return;
        }

        // blacklist the random magazine
        if ('random' === $object->magazine->name) {
            return;
        }

        $activityObject = $this->activityFactory->create($object);
        $likeActivity = $this->likeWrapper->build($this->personFactory->getActivityPubId($user), $activityObject);

        if ($message->undo) {
            $likeActivity = $this->undoWrapper->build($likeActivity);
        }

        $activity = $this->announceWrapper->build(
            $this->urlGenerator->generate('ap_magazine', ['name' => $object->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            $likeActivity
        );

        $inboxes = array_filter(array_unique(array_merge(
            $this->magazineRepository->findAudience($object->magazine),
            $this->userRepository->findAudience($user),
            [$object->user->apInboxUrl]
        )));
        $this->deliverManager->deliver($inboxes, $activity);
    }
}

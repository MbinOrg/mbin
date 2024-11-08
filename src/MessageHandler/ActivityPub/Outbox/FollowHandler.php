<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\FollowMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Wrapper\FollowWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FollowHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly FollowWrapper $followWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly ApHttpClient $apHttpClient,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(FollowMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof FollowMessage)) {
            throw new \LogicException();
        }

        $follower = $this->userRepository->find($message->followerId);
        if ($message->magazine) {
            $following = $this->magazineRepository->find($message->followingId);
        } else {
            $following = $this->userRepository->find($message->followingId);
        }

        $followObject = $this->followWrapper->build(
            $this->activityPubManager->getActorProfileId($follower),
            $followingProfileId = $this->activityPubManager->getActorProfileId($following),
        );

        if ($message->unfollow) {
            $followObject = $this->undoWrapper->build($followObject);
        }

        $inbox = $this->apHttpClient->getInboxUrl($followingProfileId);

        $this->deliverManager->deliver([$inbox], $followObject);
    }
}

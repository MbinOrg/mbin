<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Message\ActivityPub\Outbox\FollowMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\Wrapper\FollowWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FollowHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly FollowWrapper $followWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
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

        $followObject = $this->activityRepository->findFirstActivitiesByTypeAndObject('Follow', $following);
        if (null === $followObject) {
            $followObject = $this->followWrapper->build($follower, $following);
        }

        if ($message->unfollow) {
            $followObject = $this->undoWrapper->build($followObject);
        }

        $json = $this->activityJsonBuilder->buildActivityJson($followObject);
        $this->deliverManager->deliver([$following->apInboxUrl], $json);
    }
}

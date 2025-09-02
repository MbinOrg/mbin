<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\MagazineBan;
use App\Entity\User;
use App\Factory\ActivityPub\BlockFactory;
use App\Message\ActivityPub\Outbox\BlockMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\MagazineBanRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\DeliverManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class BlockHandler extends MbinMessageHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        private readonly MagazineBanRepository $magazineBanRepository,
        private readonly BlockFactory $blockFactory,
        private readonly UndoWrapper $undoWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly DeliverManager $deliverManager,
        private readonly MagazineRepository $magazineRepository,
        private readonly UserRepository $userRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UserManager $userManager,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(BlockMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof BlockMessage) {
            throw new \LogicException();
        }

        if (null !== $message->magazineBanId) {
            $ban = $this->magazineBanRepository->find($message->magazineBanId);
            if (null === $ban) {
                throw new UnrecoverableMessageHandlingException("there is no ban with id $message->magazineBanId");
            }
            $this->handleMagazineBan($ban);
        } elseif (null !== $message->bannedUserId) {
            $bannedUser = $this->userRepository->find($message->bannedUserId);
            $actor = $this->userRepository->find($message->actor);
            if (null === $bannedUser) {
                throw new UnrecoverableMessageHandlingException("there is no user with id $message->bannedUserId");
            }
            if (null === $actor) {
                throw new UnrecoverableMessageHandlingException("there is no user with id $message->actor");
            }
            $this->handleUserBan($bannedUser, $actor);
        } else {
            throw new UnrecoverableMessageHandlingException('nothing to do. `magazineBanId` and `bannedUserId` are both null');
        }
    }

    private function handleMagazineBan(MagazineBan $ban): void
    {
        $isUndo = null !== $ban->expiredAt && $ban->expiredAt < new \DateTime();

        $actor = $ban->bannedBy;
        if (null === $actor) {
            throw new UnrecoverableMessageHandlingException('An actor is needed to ban a user');
        } elseif (null !== $actor->apId) {
            throw new UnrecoverableMessageHandlingException("$actor->username is not a local user");
        }

        if ($isUndo) {
            $activity = $this->activityRepository->findFirstActivitiesByTypeAndObject('Block', $ban) ?? $this->blockFactory->createActivityFromMagazineBan($ban);
            $activity = $this->undoWrapper->build($activity);
        } else {
            $activity = $this->blockFactory->createActivityFromMagazineBan($ban);
        }
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->deliverManager->deliver($this->magazineRepository->findAudience($ban->magazine), $json);
    }

    private function handleUserBan(User $bannedUser, User $actor): void
    {
        $isUndo = !$bannedUser->isBanned;

        if ($isUndo) {
            $activity = $this->activityRepository->findFirstActivitiesByTypeAndObject('Block', $bannedUser) ?? $this->blockFactory->createActivityFromInstanceBan($bannedUser, $actor);
            $activity = $this->undoWrapper->build($activity);
        } else {
            $activity = $this->blockFactory->createActivityFromInstanceBan($bannedUser, $actor);
        }
        $json = $this->activityJsonBuilder->buildActivityJson($activity);
        $inboxes = $this->userManager->getAllInboxesOfInteractions($bannedUser);

        $this->deliverManager->deliver($inboxes, $json);
    }
}

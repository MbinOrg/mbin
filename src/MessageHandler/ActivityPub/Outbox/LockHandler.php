<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Factory\ActivityPub\LockFactory;
use App\Message\ActivityPub\Outbox\LockMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LockHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly SettingsManager $settingsManager,
        private readonly LockFactory $factory,
        private readonly DeliverManager $deliverManager,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly EntryRepository $entryRepository,
        private readonly PostRepository $postRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly UndoWrapper $undoWrapper,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(LockMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof LockMessage)) {
            throw new \LogicException();
        }

        $actor = $this->userRepository->find($message->actorId);
        if (null !== $message->entryId) {
            $object = $this->entryRepository->find($message->entryId);
        } elseif (null !== $message->postId) {
            $object = $this->postRepository->find($message->postId);
        } else {
            throw new \LogicException('There has to be either an entry id or a post id');
        }
        $magazine = $object->magazine;
        if ($magazine->apId) {
            $audience = [$magazine->apInboxUrl];
        } else {
            if ('random' === $magazine->name) {
                // do not federate the random magazine
                return;
            }
            $audience = $this->magazineRepository->findAudience($magazine);
        }

        $userAudience = $this->userRepository->findAudience($actor);
        $audience = array_filter(array_unique(array_merge($userAudience, $audience)));

        if ($object->isLocked) {
            $activity = $this->factory->build($actor, $object);
        } else {
            $activity = $this->activityRepository->findFirstActivitiesByTypeObjectAndActor('Lock', $object, $actor);
            if (null === $activity) {
                $activity = $this->factory->build($actor, $object);
            }
            $activity = $this->undoWrapper->build($activity, $actor);
        }
        $json = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->deliverManager->deliver($audience, $json);
    }
}

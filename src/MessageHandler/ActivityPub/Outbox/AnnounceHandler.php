<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\AnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class AnnounceHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly KernelInterface $kernel,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly CreateWrapper $createWrapper,
        private readonly ActivityPubManager $activityPubManager,
        private readonly DeliverManager $deliverManager,
        private readonly SettingsManager $settingsManager,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(AnnounceMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof AnnounceMessage)) {
            throw new \LogicException();
        }

        if (null !== $message->userId) {
            $actor = $this->userRepository->find($message->userId);
        } elseif (null !== $message->magazineId) {
            $actor = $this->magazineRepository->find($message->magazineId);
        } else {
            throw new UnrecoverableMessageHandlingException('no actor was specified');
        }

        $object = $this->entityManager->getRepository($message->objectType)->find($message->objectId);

        if ($actor instanceof Magazine && ($object instanceof Entry || $object instanceof Post || $object instanceof EntryComment || $object instanceof PostComment)) {
            $createActivity = $this->activityRepository->findFirstActivitiesByTypeAndObject('Create', $object);
            if (null === $createActivity) {
                if (null === $object->apId) {
                    $createActivity = $this->createWrapper->build($object);
                } else {
                    throw new UnrecoverableMessageHandlingException('We need a create activity to announce objects, but none was found and the object is from a remote instance, so we cannot build a create activity');
                }
            }
            $activity = $this->announceWrapper->build($actor, $createActivity, true);
        } else {
            $activity = $this->announceWrapper->build($actor, $object, true);
        }

        if ($message->removeAnnounce) {
            $activity = $this->undoWrapper->build($activity);
        }

        $inboxes = array_merge(
            $this->magazineRepository->findAudience($object->magazine),
            [$object->user->apInboxUrl, $object->magazine->apId ? $object->magazine->apInboxUrl : null]
        );

        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        if ($actor instanceof User) {
            $inboxes = array_merge(
                $inboxes,
                $this->userRepository->findAudience($actor),
                $this->activityPubManager->createInboxesFromCC($json, $actor),
            );
        } elseif ($actor instanceof Magazine) {
            if ('random' === $actor->name) {
                // do not federate the random magazine
                return;
            }
            $createHost = parse_url($object->apId, PHP_URL_HOST);
            $inboxes = array_filter(array_merge(
                $inboxes,
                $this->magazineRepository->findAudience($actor),
            ), fn ($item) => null !== $item and $createHost !== parse_url($item, PHP_URL_HOST));
        }

        $inboxes = array_filter(array_unique($inboxes));
        $this->deliverManager->deliver($inboxes, $json);
    }
}

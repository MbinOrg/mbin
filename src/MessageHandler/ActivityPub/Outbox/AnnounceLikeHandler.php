<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Outbox\AnnounceLikeMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\AnnounceWrapper;
use App\Service\ActivityPub\Wrapper\UndoWrapper;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class AnnounceLikeHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly KernelInterface $kernel,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnnounceWrapper $announceWrapper,
        private readonly UndoWrapper $undoWrapper,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(AnnounceLikeMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof AnnounceLikeMessage)) {
            throw new \LogicException();
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

        if (null === $message->likeMessageId) {
            $this->logger->warning('Got an AnnounceLikeMessage without a remote like id, probably an old message though');

            return;
        }
        if (false === filter_var($message->likeMessageId, FILTER_VALIDATE_URL)) {
            $this->logger->warning('Got an AnnounceLikeMessage without a valid remote like id: {url}', ['url' => $message->likeMessageId]);

            return;
        }

        $this->logger->debug('got AnnounceLikeMessage: {m}', ['m' => json_encode($message)]);
        $this->logger->debug('building like activity for: {a}', ['a' => json_encode($object)]);

        if (!$message->undo) {
            $likeActivity = $message->likeMessageId;
        } else {
            $likeActivity = $this->undoWrapper->build($message->likeMessageId, $user);
        }

        $activity = $this->announceWrapper->build($object->magazine, $likeActivity);
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        // send the announcement only to the subscribers of the magazine
        $inboxes = array_filter(
            $this->magazineRepository->findAudience($object->magazine)
        );
        $this->deliverManager->deliver($inboxes, $json);
    }
}

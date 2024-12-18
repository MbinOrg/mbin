<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\ActivityPub\Outbox\AnnounceLikeMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Service\ActivityPubManager;
use App\Service\FavouriteManager;
use App\Service\VoteManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class LikeHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly VoteManager $voteManager,
        private readonly MessageBusInterface $bus,
        private readonly FavouriteManager $favouriteManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(LikeMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof LikeMessage)) {
            throw new \LogicException();
        }
        if (!isset($message->payload['type'])) {
            return;
        }

        $chainDispatchCallback = function (array $object, ?string $adjustedUrl) use ($message) {
            if ($adjustedUrl) {
                $this->logger->info('[LikeHandler::doWork] Got an adjusted url: {url}, using that instead of {old}', ['url' => $adjustedUrl, 'old' => $message->payload['object']['id'] ?? $message->payload['object']]);
                $message->payload['object'] = $adjustedUrl;
            }
            $this->bus->dispatch(new ChainActivityMessage([$object], like: $message->payload));
        };

        if ('Like' === $message->payload['type']) {
            $entity = $this->activityPubManager->getEntityObject($message->payload['object'], $message->payload, $chainDispatchCallback);
            if (!$entity) {
                return;
            }

            $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
            // Check if actor and entity aren't empty
            if (!empty($actor) && !empty($entity)) {
                $$this->favouriteManager->toggle($actor, $entity, FavouriteManager::TYPE_LIKE);
            }
        } elseif ('Undo' === $message->payload['type']) {
            if ('Like' === $message->payload['object']['type']) {
                $entity = $this->activityPubManager->getEntityObject($message->payload['object']['object'], $message->payload, $chainDispatchCallback);
                if (!$entity) {
                    return;
                }

                $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
                // Check if actor and entity aren't empty
                if (!empty($actor) && !empty($entity)) {
                    $$this->favouriteManager->toggle($actor, $entity, FavouriteManager::TYPE_UNLIKE);
                    $this->voteManager->removeVote($entity, $actor);
                }
            }
        }

        if (isset($entity) and isset($actor) and ($entity instanceof Entry or $entity instanceof EntryComment or $entity instanceof Post or $entity instanceof PostComment)) {
            if (!$entity->magazine->apId and $actor->apId and 'random' !== $entity->magazine->name) {
                // local magazine, but remote user. Don't announce for random magazine
                $this->bus->dispatch(new AnnounceLikeMessage($actor->getId(), $entity->getId(), \get_class($entity), 'Undo' === $message->payload['type']));
            }
        }
    }
}

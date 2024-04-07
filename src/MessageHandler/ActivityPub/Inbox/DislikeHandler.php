<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Contracts\VotableInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Service\ActivityPubManager;
use App\Service\VoteManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DislikeHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly MessageBusInterface $bus,
        private readonly VoteManager $voteManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DislikeMessage $message): void
    {
        if (!isset($message->payload['type'])) {
            return;
        }

        $chainDispatchCallback = function (array $object, ?string $adjustedUrl) use ($message) {
            if ($adjustedUrl) {
                $this->logger->info('got an adjusted url: {url}, using that instead of {old}', ['url' => $adjustedUrl, 'old' => $message->payload['object']['id'] ?? $message->payload['object']]);
                $message->payload['object'] = $adjustedUrl;
            }
            $this->bus->dispatch(new ChainActivityMessage([$object], dislike: $message->payload));
        };

        if ('Dislike' === $message->payload['type']) {
            $entity = $this->activityPubManager->getEntityObject($message->payload['object'], $message->payload, $chainDispatchCallback);
            if (!$entity) {
                return;
            }

            $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
            // Check if actor and entity aren't empty
            if (!empty($actor) && !empty($entity)) {
                if ($actor instanceof User && ($entity instanceof Entry || $entity instanceof EntryComment || $entity instanceof Post || $entity instanceof PostComment)) {
                    $this->voteManager->vote(VotableInterface::VOTE_DOWN, $entity, $actor);
                }
            }
        } elseif ('Undo' === $message->payload['type']) {
            if ('Dislike' === $message->payload['object']['type']) {
                $entity = $this->activityPubManager->getEntityObject($message->payload['object']['object'], $message->payload, $chainDispatchCallback);
                if (!$entity) {
                    return;
                }

                $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
                // Check if actor and entity aren't empty
                if ($actor instanceof User && ($entity instanceof Entry || $entity instanceof EntryComment || $entity instanceof Post || $entity instanceof PostComment)) {
                    $this->voteManager->removeVote($entity, $actor);
                }
            }
        }
    }
}

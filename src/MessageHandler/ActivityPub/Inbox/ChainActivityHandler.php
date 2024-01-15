<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\ActivityPub\Outbox\AnnounceMessage as OutboxAnnounceMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ChainActivityHandler
{
    public function __construct(
        private readonly ApHttpClient $client,
        private readonly MessageBusInterface $bus,
        private readonly ApActivityRepository $repository,
        private readonly Note $note,
        private readonly Page $page
    ) {
    }

    public function __invoke(ChainActivityMessage $message): void
    {
        if ($message->parent) {
            $this->unloadStack($message->chain, $message->parent, $message->announce, $message->like);

            return;
        }

        $object = end($message->chain);
        if (!empty($object)) {
            // Handle parent objects
            if (isset($object['inReplyTo']) && $object['inReplyTo']) {
                if ($existed = $this->repository->findByObjectId($object['inReplyTo'])) {
                    $this->bus->dispatch(
                        new ChainActivityMessage($message->chain, $existed, $message->announce, $message->like)
                    );

                    return;
                }

                $message->chain[] = $this->client->getActivityObject($object['inReplyTo']);
                $this->bus->dispatch(new ChainActivityMessage($message->chain, null, $message->announce, $message->like));

                return;
            }

            $entity = match ($this->getType($object)) {
                'Note' => $this->note->create($object),
                'Page' => $this->page->create($object),
                default => null
            };

            if (!$entity) {
                if ($message->announce && $message->announce['object'] === $object['object']) {
                    $this->unloadStack($message->chain, $message->parent, $message->announce, $message->like);
                }

                if ($message->like && $message->like['object'] === $object['object']) {
                    $this->unloadStack($message->chain, $message->parent, $message->announce, $message->like);
                }

                return;
            }

            array_pop($message->chain);
        }

        if (!empty($entity)) {
            $this->bus->dispatch(
                new ChainActivityMessage($message->chain, [
                    'id' => $entity->getId(),
                    'type' => \get_class($entity),
                ], $message->announce, $message->like)
            );
        }
    }

    private function unloadStack(array $chain, array $parent, array $announce = null, array $like = null): void
    {
        $object = end($chain);

        if (!empty($object)) {
            $createdObject = match ($this->getType($object)) {
                'Question', 'Note' => $this->note->create($object),
                'Page' => $this->page->create($object),
                default => null
            };

            if ($createdObject and ($createdObject instanceof EntryComment or $createdObject instanceof Post or $createdObject instanceof PostComment)) {
                if (null !== $createdObject->apId and null === $createdObject->magazine->apId) {
                    // local magazine, but remote post
                    $this->bus->dispatch(new OutboxAnnounceMessage(null, $createdObject->magazine->getId(), $createdObject->getId(), \get_class($createdObject)));
                }
            }

            array_pop($chain);

            if (\count(array_filter($chain))) {
                $this->bus->dispatch(new ChainActivityMessage($chain, $parent, $announce, $like));

                return;
            }
        }

        if ($announce) {
            $this->bus->dispatch(new AnnounceMessage($announce));

            return;
        }

        if ($like) {
            $this->bus->dispatch(new LikeMessage($like));

            return;
        }
    }

    private function getType(array $object): string
    {
        if (isset($object['object']) && \is_array($object['object'])) {
            return $object['object']['type'];
        }

        return $object['type'];
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Message\ActivityPub\Inbox\UpdateMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApObjectExtractor;
use App\Service\ActivityPubManager;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\MessageManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class UpdateHandler
{
    private array $payload;

    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryManager $entryManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostManager $postManager,
        private readonly PostCommentManager $postCommentManager,
        private readonly EntryFactory $entryFactory,
        private readonly EntryCommentFactory $entryCommentFactory,
        private readonly PostFactory $postFactory,
        private readonly PostCommentFactory $postCommentFactory,
        private readonly ApObjectExtractor $objectExtractor,
        private readonly MessageManager $messageManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(UpdateMessage $message): void
    {
        $this->payload = $message->payload;

        try {
            $actor = $this->activityPubManager->findRemoteActor($message->payload['actor']);
        } catch (\Exception) {
            return;
        }

        $object = $this->apActivityRepository->findByObjectId($message->payload['object']['id']);

        if (!$object) {
            return;
        }

        $object = $this->entityManager->getRepository($object['type'])->find((int) $object['id']);
        if ($object instanceof Entry) {
            $this->editEntry($object, $actor);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), $message->payload, $actor->apInboxUrl));
            }
        } elseif ($object instanceof EntryComment) {
            $this->editEntryComment($object, $actor);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), $message->payload, $actor->apInboxUrl));
            }
        } elseif ($object instanceof Post) {
            $this->editPost($object, $actor);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), $message->payload, $actor->apInboxUrl));
            }
        } elseif ($object instanceof PostComment) {
            $this->editPostComment($object, $actor);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), $message->payload, $actor->apInboxUrl));
            }
        } elseif ($object instanceof Message) {
            $this->editMessage($object, $actor);
        }
    }

    private function editEntry(Entry $entry, User $user): void
    {
        $dto = $this->entryFactory->createDto($entry);

        $dto->title = $this->payload['object']['name'];

        $this->extractChanges($dto);
        $this->entryManager->edit($entry, $dto);
    }

    private function editEntryComment(EntryComment $comment, User $user): void
    {
        $dto = $this->entryCommentFactory->createDto($comment);

        $this->extractChanges($dto);

        $this->entryCommentManager->edit($comment, $dto);
    }

    private function editPost(Post $post, User $user): void
    {
        $dto = $this->postFactory->createDto($post);

        $this->extractChanges($dto);

        $this->postManager->edit($post, $dto);
    }

    private function editPostComment(PostComment $comment, User $user): void
    {
        $dto = $this->postCommentFactory->createDto($comment);

        $this->extractChanges($dto);

        $this->postCommentManager->edit($comment, $dto);
    }

    private function extractChanges(EntryDto|EntryCommentDto|PostDto|PostCommentDto $dto): void
    {
        if (!empty($this->payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($this->payload['object']);
        } else {
            $dto->body = null;
        }
        $dto->apLikeCount = $this->activityPubManager->extractRemoteLikeCount($this->payload['object']);
        $dto->apDislikeCount = $this->activityPubManager->extractRemoteDislikeCount($this->payload['object']);
        $dto->apShareCount = $this->activityPubManager->extractRemoteShareCount($this->payload['object']);
    }

    private function editMessage(Message $message, User $user): void
    {
        if ($this->messageManager->canUserEditMessage($message, $user)) {
            $this->messageManager->editMessage($message, $this->payload['object']);
        } else {
            $this->logger->warning(
                'Got an update message from a user that is not allowed to edit it. Update actor: {ua}. Original Author: {oa}',
                ['ua' => $user->apId ?? $user->username, 'oa' => $message->sender->apId ?? $message->sender->username]
            );
        }
    }
}

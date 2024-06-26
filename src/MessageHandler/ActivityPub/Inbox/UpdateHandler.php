<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

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

        if (Entry::class === \get_class($object)) {
            $this->editEntry($object, $actor);
        } elseif (EntryComment::class === \get_class($object)) {
            $this->editEntryComment($object, $actor);
        } elseif (Post::class === \get_class($object)) {
            $this->editPost($object, $actor);
        } elseif (PostComment::class === \get_class($object)) {
            $this->editPostComment($object, $actor);
        } elseif (Message::class === \get_class($object)) {
            $this->editMessage($object, $actor);
        }

        // Dead-code introduced by Ernest "Temp disable handler dispatch", in commit:
        // 4573e87f91923b9a5758e0dfacb3870d55ef1166
        //
        //        if (null === $object->magazine->apId) {
        //            $this->bus->dispatch(
        //                new \App\Message\ActivityPub\Outbox\UpdateMessage(
        //                    $actor->getId(),
        //                    $object->getId(),
        //                    get_class($object)
        //                )
        //            );
        //        }
    }

    private function editEntry(Entry $entry, User $user)
    {
        $dto = $this->entryFactory->createDto($entry);

        $dto->title = $this->payload['object']['name'];

        if (!empty($this->payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($this->payload['object']);
        } else {
            $dto->body = null;
        }

        $this->entryManager->edit($entry, $dto);
    }

    private function editEntryComment(EntryComment $comment, User $user)
    {
        $dto = $this->entryCommentFactory->createDto($comment);

        if (!empty($this->payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($this->payload['object']);
        } else {
            $dto->body = null;
        }

        $this->entryCommentManager->edit($comment, $dto);
    }

    private function editPost(Post $post, User $user)
    {
        $dto = $this->postFactory->createDto($post);

        if (!empty($this->payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($this->payload['object']);
        } else {
            $dto->body = null;
        }

        $this->postManager->edit($post, $dto);
    }

    private function editPostComment(PostComment $comment, User $user)
    {
        $dto = $this->postCommentFactory->createDto($comment);

        if (!empty($this->payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($this->payload['object']);
        } else {
            $dto->body = null;
        }

        $this->postCommentManager->edit($comment, $dto);
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

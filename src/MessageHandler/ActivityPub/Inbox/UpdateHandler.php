<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
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
use App\Service\PostCommentManager;
use App\Service\PostManager;
use Doctrine\ORM\EntityManagerInterface;
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
        }

        if (EntryComment::class === \get_class($object)) {
            $this->editEntryComment($object, $actor);
        }

        if (Post::class === \get_class($object)) {
            $this->editPost($object, $actor);
        }

        if (PostComment::class === \get_class($object)) {
            $this->editPostComment($object, $actor);
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
}

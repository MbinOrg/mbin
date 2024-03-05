<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\DeleteMessage;
use App\Message\DeleteUserMessage;
use App\Repository\ApActivityRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPubManager;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DeleteHandler
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EntryManager $entryManager,
        private readonly EntryCommentManager $entryCommentManager,
        private readonly PostManager $postManager,
        private readonly PostCommentManager $postCommentManager
    ) {
    }

    public function __invoke(DeleteMessage $message): void
    {
        try {
            $actor = $this->activityPubManager->findRemoteActor($message->payload['actor']);
        } catch (\Exception) {
            return;
        }

        $id = \is_array($message->payload['object']) ? $message->payload['object']['id'] : $message->payload['object'];
        $object = $this->apActivityRepository->findByObjectId($id);

        if (!$object) {
            $user = $this->userRepository->findOneBy(['apId' => $id]);
            if ($actor->apDomain === $user->apDomain) {
                // only users of the same server can delete each other.
                // Since the server of both is in charge as to which user can delete each other.
                $object = [
                    'type' => User::class,
                    'id' => $user->getId(),
                ];
            }
        }

        if (!$object) {
            return;
        }

        $object = $this->entityManager->getRepository($object['type'])->find((int) $object['id']);

        if ($object instanceof Entry) {
            $this->deleteEntry($object, $actor);
        } elseif ($object instanceof EntryComment) {
            $this->deleteEntryComment($object, $actor);
        } elseif ($object instanceof Post) {
            $this->deletePost($object, $actor);
        } elseif ($object instanceof PostComment) {
            $this->deletePostComment($object, $actor);
        } elseif ($object instanceof User) {
            $this->bus->dispatch(new DeleteUserMessage($object->getId()));
        }
    }

    private function deleteEntry(Entry $entry, User $user): void
    {
        $this->entryManager->delete($user, $entry);
    }

    private function deleteEntryComment(EntryComment $comment, User $user): void
    {
        $this->entryCommentManager->delete($user, $comment);
    }

    private function deletePost(Post $post, User $user): void
    {
        $this->postManager->delete($user, $post);
    }

    private function deletePostComment(PostComment $comment, User $user): void
    {
        $this->postCommentManager->delete($user, $comment);
    }
}

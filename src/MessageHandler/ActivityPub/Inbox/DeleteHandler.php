<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\DeleteMessage;
use App\Message\Contracts\MessageInterface;
use App\Message\DeleteUserMessage;
use App\MessageHandler\MbinMessageHandler;
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
class DeleteHandler extends MbinMessageHandler
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
        parent::__construct($this->entityManager);
    }

    public function __invoke(DeleteMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof DeleteMessage)) {
            throw new \LogicException();
        }
        $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);

        $id = \is_array($message->payload['object']) ? $message->payload['object']['id'] : $message->payload['object'];
        $object = $this->apActivityRepository->findByObjectId($id);

        if (!$object && $actor) {
            $user = $this->userRepository->findOneBy(['apProfileId' => $id]);
            if ($actor instanceof User && $user instanceof User && $actor->apDomain === $user->apDomain) {
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

        $entity = $this->entityManager->getRepository($object['type'])->find((int) $object['id']);

        if ($entity instanceof Entry) {
            $this->deleteEntry($entity, $actor);
        } elseif ($entity instanceof EntryComment) {
            $this->deleteEntryComment($entity, $actor);
        } elseif ($entity instanceof Post) {
            $this->deletePost($entity, $actor);
        } elseif ($entity instanceof PostComment) {
            $this->deletePostComment($entity, $actor);
        } elseif ($entity instanceof User) {
            $this->bus->dispatch(new DeleteUserMessage($entity->getId()));
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

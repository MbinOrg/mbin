<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\PostCommentDto;
use App\DTO\PostDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\ImageFactory;
use App\Factory\MagazineFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Message\ActivityPub\Inbox\UpdateMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\ActivityPub\UpdateActorMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApObjectExtractor;
use App\Service\ActivityPubManager;
use App\Service\EntryCommentManager;
use App\Service\EntryManager;
use App\Service\MagazineManager;
use App\Service\MessageManager;
use App\Service\PostCommentManager;
use App\Service\PostManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class UpdateHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly KernelInterface $kernel,
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
        private readonly MagazineManager $magazineManager,
        private readonly MagazineFactory $magazineFactory,
        private readonly ImageFactory $imageFactory,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(UpdateMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UpdateMessage)) {
            throw new \LogicException();
        }
        $payload = $message->payload;
        $this->logger->debug('[UpdateHandler::doWork] received Update activity: {json}', ['json' => $payload]);

        try {
            $actor = $this->activityPubManager->findRemoteActor($payload['actor']);
        } catch (\Exception) {
            return;
        }

        $object = $this->apActivityRepository->findByObjectId($payload['object']['id']);

        if ($object) {
            $this->editActivity($object, $actor, $payload);

            return;
        }

        $object = $this->activityPubManager->findActorOrCreate($payload['object']['id']);
        if ($object instanceof User) {
            $this->updateUser($object, $actor);

            return;
        }

        if ($object instanceof Magazine) {
            $this->updateMagazine($object, $actor, $payload);

            return;
        }

        $this->logger->warning("[UpdateHandler::doWork] didn't know what to do with the update activity concerning '{id}'. We don't have a local object that has this id", ['id' => $payload['object']['id']]);
    }

    private function editActivity(array $object, User $actor, array $payload): void
    {
        $object = $this->entityManager->getRepository($object['type'])->find((int) $object['id']);

        if ($object instanceof Entry) {
            $this->editEntry($object, $actor, $payload);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), null, $actor->apInboxUrl, null, $payload['id']));
            }
        } elseif ($object instanceof EntryComment) {
            $this->editEntryComment($object, $actor, $payload);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), null, $actor->apInboxUrl, null, $payload['id']));
            }
        } elseif ($object instanceof Post) {
            $this->editPost($object, $actor, $payload);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), null, $actor->apInboxUrl, null, $payload['id']));
            }
        } elseif ($object instanceof PostComment) {
            $this->editPostComment($object, $actor, $payload);
            if (null === $object->magazine->apId) {
                $this->bus->dispatch(new GenericAnnounceMessage($object->magazine->getId(), null, $actor->apInboxUrl, null, $payload['id']));
            }
        } elseif ($object instanceof Message) {
            $this->editMessage($object, $actor, $payload);
        }
    }

    private function editEntry(Entry $entry, User $user, array $payload): void
    {
        if (!$this->entryManager->canUserEditEntry($entry, $user)) {
            $this->logger->warning('[UpdateHandler::editEntry] User {u} tried to edit entry {et} ({eId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'et' => $entry->title, 'eId' => $entry->getId()]);

            return;
        }
        $dto = $this->entryFactory->createDto($entry);

        $dto->title = $payload['object']['name'];

        $this->extractChanges($dto, $payload);
        $this->entryManager->edit($entry, $dto, $user);
    }

    private function editEntryComment(EntryComment $comment, User $user, array $payload): void
    {
        if (!$this->entryCommentManager->canUserEditComment($comment, $user)) {
            $this->logger->warning('[UpdateHandler::editEntryComment] User {u} tried to edit entry comment {et} ({eId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'et' => $comment->getShortTitle(), 'eId' => $comment->getId()]);

            return;
        }
        $dto = $this->entryCommentFactory->createDto($comment);

        $this->extractChanges($dto, $payload);

        $this->entryCommentManager->edit($comment, $dto, $user);
    }

    private function editPost(Post $post, User $user, array $payload): void
    {
        if (!$this->postManager->canUserEditPost($post, $user)) {
            $this->logger->warning('[UpdateHandler::editPost] User {u} tried to edit post {pt} ({pId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'pt' => $post->getShortTitle(), 'pId' => $post->getId()]);

            return;
        }
        $dto = $this->postFactory->createDto($post);

        $this->extractChanges($dto, $payload);

        $this->postManager->edit($post, $dto, $user);
    }

    private function editPostComment(PostComment $comment, User $user, array $payload): void
    {
        if (!$this->postCommentManager->canUserEditPostComment($comment, $user)) {
            $this->logger->warning('[UpdateHandler::editPostComment] User {u} tried to edit post comment {pt} ({pId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'pt' => $comment->getShortTitle(), 'pId' => $comment->getId()]);

            return;
        }
        $dto = $this->postCommentFactory->createDto($comment);

        $this->extractChanges($dto, $payload);

        $this->postCommentManager->edit($comment, $dto, $user);
    }

    private function extractChanges(EntryDto|EntryCommentDto|PostDto|PostCommentDto $dto, array $payload): void
    {
        $this->logger->debug('[UpdateHandler::extractChanges] extracting changes from {c}', ['c' => \get_class($dto)]);
        if (!empty($payload['object']['content'])) {
            $dto->body = $this->objectExtractor->getMarkdownBody($payload['object']);
        } else {
            $dto->body = null;
        }
        if (!empty($payload['object']['attachment'])) {
            $this->logger->debug('[UpdateHandler::extractChanges] was not empty :)');
            $image = $this->activityPubManager->handleImages($payload['object']['attachment']);
            if (null !== $image) {
                $dto->image = $this->imageFactory->createDto($image);
            }
            if ($dto instanceof EntryDto) {
                $url = ActivityPubManager::extractUrlFromAttachment($payload['object']['attachment']);
                $dto->url = $url;
                $this->logger->debug('[UpdateHandler::extractChanges] setting url to {u} which was extracted from the attachment array', ['u' => $url]);
            }
        }
        $dto->apLikeCount = $this->activityPubManager->extractRemoteLikeCount($payload['object']);
        $dto->apDislikeCount = $this->activityPubManager->extractRemoteDislikeCount($payload['object']);
        $dto->apShareCount = $this->activityPubManager->extractRemoteShareCount($payload['object']);
    }

    private function editMessage(Message $message, User $user, array $payload): void
    {
        if ($this->messageManager->canUserEditMessage($message, $user)) {
            $this->messageManager->editMessage($message, $payload['object']);
        } else {
            $this->logger->warning(
                '[UpdateHandler::editMessage] Got an update message from a user that is not allowed to edit it. Update actor: {ua}. Original Author: {oa}',
                ['ua' => $user->apId ?? $user->username, 'oa' => $message->sender->apId ?? $message->sender->username]
            );
        }
    }

    private function updateUser(User $user, User $actor): void
    {
        if ($user->canUpdateUser($actor)) {
            if (null !== $user->apId) {
                $this->bus->dispatch(new UpdateActorMessage($user->apProfileId, force: true));
            }
        } else {
            $this->logger->warning('[UpdateHandler::updateUser] User {u1} wanted to update user {u2} without being allowed to do so', ['u1' => $actor->apId ?? $actor->username, 'u2' => $user->apId ?? $user->username]);
        }
    }

    private function updateMagazine(Magazine $magazine, User $actor, array $payload): void
    {
        if ($magazine->canUpdateMagazine($actor)) {
            if (null !== $magazine->apId) {
                $this->bus->dispatch(new UpdateActorMessage($magazine->apProfileId, force: true));
            }
        } else {
            $this->logger->warning('[UpdateHandler::updateMagazine] User {u} wanted to update magazine {m} without being allowed to do so', ['u' => $actor->apId ?? $actor->username, 'm' => $magazine->apId ?? $magazine->name]);
        }
    }
}

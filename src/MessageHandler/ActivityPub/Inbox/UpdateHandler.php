<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\DTO\MagazineThemeDto;
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
use App\Message\DeleteImageMessage;
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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class UpdateHandler extends MbinMessageHandler
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
        private readonly MagazineManager $magazineManager,
        private readonly MagazineFactory $magazineFactory,
        private readonly ImageFactory $imageFactory,
    ) {
        parent::__construct($this->entityManager);
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
        $this->payload = $message->payload;
        $this->logger->debug('received Update activity: {json}', ['json' => $this->payload]);

        try {
            $actor = $this->activityPubManager->findRemoteActor($message->payload['actor']);
        } catch (\Exception) {
            return;
        }

        $object = $this->apActivityRepository->findByObjectId($message->payload['object']['id']);

        if ($object) {
            $this->editActivity($object, $message, $actor);

            return;
        }

        $object = $this->activityPubManager->findActorOrCreate($message->payload['object']['id']);
        if ($object instanceof User) {
            $this->updateUser($object, $actor);

            return;
        }

        if ($object instanceof Magazine) {
            $this->updateMagazine($object, $actor);

            return;
        }

        $this->logger->warning("didn't know what to do with the update activity concerning '{id}'. We don't have a local object that has this id", ['id' => $message->payload['object']['id']]);
    }

    private function editActivity(array $object, UpdateMessage $message, User $actor): void
    {
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
        if (!$this->entryManager->canUserEditEntry($entry, $user)) {
            $this->logger->warning('User {u} tried to edit entry {et} ({eId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'et' => $entry->title, 'eId' => $entry->getId()]);

            return;
        }
        $dto = $this->entryFactory->createDto($entry);

        $dto->title = $this->payload['object']['name'];

        $this->extractChanges($dto);
        $this->entryManager->edit($entry, $dto, $user);
    }

    private function editEntryComment(EntryComment $comment, User $user): void
    {
        if (!$this->entryCommentManager->canUserEditComment($comment, $user)) {
            $this->logger->warning('User {u} tried to edit entry comment {et} ({eId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'et' => $comment->getShortTitle(), 'eId' => $comment->getId()]);

            return;
        }
        $dto = $this->entryCommentFactory->createDto($comment);

        $this->extractChanges($dto);

        $this->entryCommentManager->edit($comment, $dto, $user);
    }

    private function editPost(Post $post, User $user): void
    {
        if (!$this->postManager->canUserEditPost($post, $user)) {
            $this->logger->warning('User {u} tried to edit post {pt} ({pId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'pt' => $post->getShortTitle(), 'pId' => $post->getId()]);

            return;
        }
        $dto = $this->postFactory->createDto($post);

        $this->extractChanges($dto);

        $this->postManager->edit($post, $dto, $user);
    }

    private function editPostComment(PostComment $comment, User $user): void
    {
        if (!$this->postCommentManager->canUserEditPostComment($comment, $user)) {
            $this->logger->warning('User {u} tried to edit post comment {pt} ({pId}), but is not allowed to', ['u' => $user->apId ?? $user->username, 'pt' => $comment->getShortTitle(), 'pId' => $comment->getId()]);

            return;
        }
        $dto = $this->postCommentFactory->createDto($comment);

        $this->extractChanges($dto);

        $this->postCommentManager->edit($comment, $dto, $user);
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

    private function updateUser(User $user, User $actor): void
    {
        if ($user->canUpdateUser($actor)) {
            if (null !== $user->apId) {
                $this->bus->dispatch(new UpdateActorMessage($user->apProfileId, force: true));
            }
        } else {
            $this->logger->warning('User {u1} wanted to update user {u2} without being allowed to do so', ['u1' => $actor->apId ?? $actor->username, 'u2' => $user->apId ?? $user->username]);
        }
    }

    private function updateMagazine(Magazine $magazine, User $actor): void
    {
        if ($magazine->canUpdateMagazine($actor)) {
            $payloadObject = $this->payload['object'];

            $themeDto = new MagazineThemeDto($magazine);
            if (isset($payloadObject['icon'])) {
                $newImage = $this->activityPubManager->handleImages([$payloadObject['icon']]);
                if ($magazine->icon && $newImage !== $magazine->icon) {
                    $this->bus->dispatch(new DeleteImageMessage($magazine->icon->getId()));
                }
                $themeDto->icon = $this->imageFactory->createDto($newImage);
            } elseif ($magazine->icon) {
                $this->magazineManager->detachIcon($magazine);
            }

            $this->magazineManager->changeTheme($themeDto);

            $dto = $this->magazineFactory->createDto($magazine);
            if ($payloadObject['name']) {
                $dto->title = $payloadObject['name'];
            } elseif ($payloadObject['preferredUsername']) {
                $dto->title = $payloadObject['preferredUsername'];
            }
            if (isset($payloadObject['summary'])) {
                $dto->description = $this->activityPubManager->extractMarkdownSummary($payloadObject);
            }
            $dto->isAdult = $payloadObject['sensitive'] ?? false;

            $this->magazineManager->edit($magazine, $dto, $actor);
        } else {
            $this->logger->warning('User {u} wanted to update magazine {m} without being allowed to do so', ['u' => $actor->apId ?? $actor->username, 'm' => $magazine->apId ?? $magazine->name]);
        }
    }
}

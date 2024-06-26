<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MessageDto;
use App\Entity\Message;
use App\Entity\MessageThread;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Repository\MessageThreadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageManager
{
    public function __construct(
        private readonly MessageThreadRepository $messageThreadRepository,
        private readonly MessageBusInterface $bus,
        private readonly ActivityPubManager $activityPubManager,
        private readonly NotificationManager $notificationManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function toThread(MessageDto $dto, User $sender, User ...$receivers): MessageThread
    {
        $thread = new MessageThread($sender, ...$receivers);
        $thread->addMessage($this->toMessage($dto, $thread, $sender));

        $this->entityManager->persist($thread);
        $this->entityManager->flush();

        return $thread;
    }

    public function toMessage(MessageDto $dto, MessageThread $thread, User $sender): Message
    {
        $message = new Message($thread, $sender, $dto->body, $dto->apId);

        $thread->setUpdatedAt();

        $this->entityManager->persist($thread);
        $this->entityManager->flush();

        $this->notificationManager->sendMessageNotification($message, $sender);
        $this->bus->dispatch(new CreateMessage($message->getId(), Message::class));

        return $message;
    }

    public function readMessages(MessageThread $thread, User $user): void
    {
        foreach ($thread->getNewMessages($user) as $message) {
            /*
             * @var Message $message
             */
            $this->readMessage($message, $user);
        }

        $this->entityManager->flush();
    }

    public function readMessage(Message $message, User $user, bool $flush = false): void
    {
        $message->status = Message::STATUS_READ;

        $this->notificationManager->readMessageNotification($message, $user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function unreadMessage(Message $message, User $user, bool $flush = false): void
    {
        $message->status = Message::STATUS_NEW;

        $this->notificationManager->unreadMessageNotification($message, $user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function canUserEditMessage(Message $message, User $user): bool
    {
        return $message->sender->apId === $user->apId || $message->sender->apDomain === $user->apDomain;
    }

    public function createMessage(array $object): Message|MessageThread
    {
        $this->logger->debug('creating message from {o}', ['o' => $object]);
        $participantIds = array_merge($object['to'] ?? [], $object['cc'] ?? []);
        $participants = array_map(fn ($participant) => $this->activityPubManager->findActorOrCreate(\is_string($participant) ? $participant : $participant['id']), $participantIds);
        $author = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        $participants[] = $author;
        $message = new MessageDto();
        $message->body = $this->activityPubManager->extractMarkdownContent($object);
        $message->apId = $object['id'] ?? null;
        $threads = $this->messageThreadRepository->findByParticipants($participants);
        if (\sizeof($threads) > 0) {
            return $this->toMessage($message, $threads[0], $author);
        } else {
            return $this->toThread($message, $author, ...$participants);
        }
    }

    public function editMessage(Message $message, array $object): void
    {
        $this->logger->debug('editing message {m}', ['m' => $message->apId]);
        $newBody = $this->activityPubManager->extractMarkdownContent($object);
        if ($message->body !== $newBody) {
            $message->body = $newBody;
            $message->editedAt = new \DateTimeImmutable();
            $this->entityManager->persist($message);
            $this->entityManager->flush();
        }
    }

    /** @return string[] */
    public function findAudience(MessageThread $thread): array
    {
        $res = [];
        foreach ($thread->participants as /* @var User $participant */ $participant) {
            if ($participant->apId && !$participant->isDeleted && !$participant->isBanned) {
                $res[] = $participant->apInboxUrl;
            }
        }

        return array_unique($res);
    }
}

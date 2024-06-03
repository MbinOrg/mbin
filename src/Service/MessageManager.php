<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MessageDto;
use App\Entity\Message;
use App\Entity\MessageThread;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Repository\ApActivityRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageManager
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ApActivityRepository $apActivityRepository,
        private readonly MessageBusInterface $bus,
        private readonly ActivityPubManager $activityPubManager,
        private readonly NotificationManager $notificationManager,
        private readonly EntityManagerInterface $entityManager,
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
        $message = new Message($thread, $sender, $dto->body);

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

    public function createMessage(array $object): null|Message|MessageThread
    {
        $participants = array_map(fn ($participant) => $this->activityPubManager->findActorOrCreate(\is_string($participant) ? $participant : $participant['id']), array_merge($this->object['to'] ?? [], $this->object['cc'] ?? []));
        $author = $this->activityPubManager->findActorOrCreate($object['attributedTo']);
        $message = new MessageDto();
        $message->body = $this->activityPubManager->extractMarkdownContent($object);
        if (!empty($this->object['inReplyTo'])) {
            return $this->toThread($message, $author, ...$participants);
        } else {
            $inReplyTo = $this->apActivityRepository->findByObjectId($object['inReplyTo']);
            if (null !== $inReplyTo) {
                if (Message::class === $inReplyTo['type']) {
                    $inReplyToMessage = $this->messageRepository->find($inReplyTo['id']);

                    return $this->toMessage($message, $inReplyToMessage->thread, $author);
                } else {
                    return $this->toThread($message, $author, ...$participants);
                }
            } else {
                $this->bus->dispatch(new ChainActivityMessage([$object]));
            }
        }

        return null;
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

<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Poll\PollEditedEvent;
use App\Event\Poll\PollPreEditedEvent;
use App\Event\Poll\PollVoteEvent;
use App\Message\ActivityPub\Outbox\PollVoteMessage;
use App\Service\Notification\PollNotificationManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class PollEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private PollNotificationManager $notificationManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PollVoteEvent::class => 'onPollVote',
            PollEditedEvent::class => 'onPollEdited',
            PollPreEditedEvent::class => 'onPollPreEdited',
        ];
    }

    public function onPollVote(PollVoteEvent $event): void
    {
        if ($event->poll->isRemote && null === $event->voter->apId) {
            // remote poll, local user -> send the vote
            foreach ($event->poll->votes as $vote) {
                $this->bus->dispatch(new PollVoteMessage($vote->uuid->toString()));
            }
        }
    }

    public function onPollEdited(PollEditedEvent $event): void
    {
    }

    public function onPollPreEdited(PollPreEditedEvent $event): void
    {
        try {
            $this->notificationManager->sendPollEditedNotification($event->poll);
        } catch (\Throwable $exception) {
            $this->logger->error('Something went wrong while sending the poll edited notifications for poll {p}: {e} - {m}', [
                'p' => $event->poll->getId(),
                'e' => \get_class($exception),
                'm' => $exception->getMessage(),
            ]);
        }
    }
}

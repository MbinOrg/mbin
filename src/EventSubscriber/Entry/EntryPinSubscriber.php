<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryPinEvent;
use App\Factory\ActivityPub\AddRemoveFactory;
use App\Message\ActivityPub\Outbox\EntryPinMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntryPinSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly AddRemoveFactory $addRemoveFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryPinEvent::class => 'onEntryPin',
        ];
    }

    public function onEntryPin(EntryPinEvent $event): void
    {
        if ($event->actor && null === $event->actor->apId && $event->entry->magazine->userIsModerator($event->actor)) {
            $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryPinMessage', ['e' => $event->entry->title, 'p' => $event->entry->sticky ? 'pinned' : 'unpinned', 'u' => $event->actor?->username ?? 'system']);
            $this->bus->dispatch(new EntryPinMessage($event->entry->getId(), $event->entry->sticky, $event->actor?->getId()));
        } elseif (null === $event->entry->magazine->apId && $event->actor && $event->entry->magazine->userIsModerator($event->actor)) {
            if (null !== $event->actor->apId) {
                if ($event->entry->sticky) {
                    $activity = $this->addRemoveFactory->buildAddPinnedPost($event->actor, $event->entry);
                } else {
                    $activity = $this->addRemoveFactory->buildRemovePinnedPost($event->actor, $event->entry);
                }
                $this->logger->debug('dispatching announce for add pin post {e} by {u} in {m}', ['e' => $event->entry->title, 'u' => $event->actor->apId, 'm' => $event->entry->magazine->name]);
                $this->bus->dispatch(new GenericAnnounceMessage($event->entry->magazine->getId(), $activity, $event->actor->apInboxUrl));
            } else {
                $this->logger->debug('entry {e} got {p} by {u}, dispatching new EntryPinMessage', ['e' => $event->entry->title, 'p' => $event->entry->sticky ? 'pinned' : 'unpinned', 'u' => $event->actor?->username ?? 'system']);
                $this->bus->dispatch(new EntryPinMessage($event->entry->getId(), $event->entry->sticky, $event->actor?->getId()));
            }
        }
    }
}

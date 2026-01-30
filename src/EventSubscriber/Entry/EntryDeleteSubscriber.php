<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Entity\Entry;
use App\Entity\User;
use App\Event\Entry\EntryBeforeDeletedEvent;
use App\Event\Entry\EntryBeforePurgeEvent;
use App\Event\Entry\EntryDeletedEvent;
use App\Message\Notification\EntryDeletedNotificationMessage;
use App\Repository\EntryRepository;
use App\Service\ActivityPub\DeleteService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntryDeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntryRepository $entryRepository,
        private readonly DeleteService $deleteService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryDeletedEvent::class => 'onEntryDeleted',
            EntryBeforePurgeEvent::class => 'onEntryBeforePurge',
            EntryBeforeDeletedEvent::class => 'onEntryBeforeDelete',
        ];
    }

    public function onEntryDeleted(EntryDeletedEvent $event): void
    {
        $this->bus->dispatch(new EntryDeletedNotificationMessage($event->entry->getId()));
    }

    public function onEntryBeforePurge(EntryBeforePurgeEvent $event): void
    {
        $event->entry->magazine->entryCount = $this->entryRepository->countEntriesByMagazine(
            $event->entry->magazine
        ) - 1;
        $this->onEntryBeforeDeleteImpl($event->user, $event->entry);
    }

    public function onEntryBeforeDelete(EntryBeforeDeletedEvent $event): void
    {
        $this->onEntryBeforeDeleteImpl($event->user, $event->entry);
    }

    public function onEntryBeforeDeleteImpl(?User $user, Entry $entry): void
    {
        $this->bus->dispatch(new EntryDeletedNotificationMessage($entry->getId()));
        $this->deleteService->announceIfNecessary($user, $entry);
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber\Entry;

use App\Event\Entry\EntryCreatedEvent;
use App\Message\ActivityPub\Outbox\CreateMessage;
use App\Message\EntryEmbedMessage;
use App\Message\LinkEmbedMessage;
use App\Message\Notification\EntryCreatedNotificationMessage;
use App\Repository\EntryRepository;
use App\Service\DomainManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class EntryCreateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly DomainManager $manager,
        private readonly EntryRepository $entryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntryCreatedEvent::class => 'onEntryCreated',
        ];
    }

    public function onEntryCreated(EntryCreatedEvent $event): void
    {
        $event->entry->magazine->entryCount = $this->entryRepository->countEntriesByMagazine($event->entry->magazine);

        $this->entityManager->flush();

        $this->manager->extract($event->entry);
        $this->bus->dispatch(new EntryEmbedMessage($event->entry->getId()));
        $this->bus->dispatch(new EntryCreatedNotificationMessage($event->entry->getId()));
        if ($event->entry->body) {
            $this->bus->dispatch(new LinkEmbedMessage($event->entry->body));
        }

        if (!$event->entry->apId) {
            $this->bus->dispatch(new CreateMessage($event->entry->getId(), \get_class($event->entry)));
        }
    }
}

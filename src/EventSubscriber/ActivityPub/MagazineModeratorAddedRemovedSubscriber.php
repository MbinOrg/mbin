<?php

declare(strict_types=1);

namespace App\EventSubscriber\ActivityPub;

use App\Entity\Magazine;
use App\Entity\MagazineLogModeratorAdd;
use App\Entity\MagazineLogModeratorRemove;
use App\Event\Magazine\MagazineModeratorAddedEvent;
use App\Event\Magazine\MagazineModeratorRemovedEvent;
use App\Message\ActivityPub\Outbox\AddMessage;
use App\Message\ActivityPub\Outbox\RemoveMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

class MagazineModeratorAddedRemovedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function onModeratorAdded(MagazineModeratorAddedEvent $event): void
    {
        // if the magazine is local then we have authority over it, otherwise the addedBy user has to be a local user
        if (!$event->magazine->apId or (null !== $event->addedBy and !$event->addedBy->apId)) {
            $this->bus->dispatch(new AddMessage($event->addedBy->getId(), $event->magazine->getId(), $event->user->getId()));
        }
        $log = new MagazineLogModeratorAdd($event->magazine, $event->user, $event->addedBy);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->deleteCache($event->magazine);
    }

    public function onModeratorRemoved(MagazineModeratorRemovedEvent $event): void
    {
        // if the magazine is local then we have authority over it, otherwise the removedBy user has to be a local user
        if (!$event->magazine->apId or (null !== $event->removedBy and !$event->removedBy->apId)) {
            $this->bus->dispatch(new RemoveMessage($event->removedBy->getId(), $event->magazine->getId(), $event->user->getId()));
        }
        $log = new MagazineLogModeratorRemove($event->magazine, $event->user, $event->removedBy);
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->deleteCache($event->magazine);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MagazineModeratorAddedEvent::class => 'onModeratorAdded',
            MagazineModeratorRemovedEvent::class => 'onModeratorRemoved',
        ];
    }

    private function deleteCache(Magazine $magazine): void
    {
        if (!$magazine->apId) {
            return;
        }

        try {
            $this->cache->delete('ap_'.hash('sha256', $magazine->apProfileId));
            $this->cache->delete('ap_'.hash('sha256', $magazine->apId));
            if (null !== $magazine->apAttributedToUrl) {
                $this->cache->delete('ap_'.hash('sha256', $magazine->apAttributedToUrl));
                $this->cache->delete('ap_collection'.hash('sha256', $magazine->apAttributedToUrl));
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->warning("There was an error while clearing the cache for magazine '{$magazine->name}' ({$magazine->getId()})");
        }
    }
}

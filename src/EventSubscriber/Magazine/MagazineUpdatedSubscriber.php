<?php

declare(strict_types=1);

namespace App\EventSubscriber\Magazine;

use App\Entity\Magazine;
use App\Event\Magazine\MagazineUpdatedEvent;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\ActivityPub\Outbox\UpdateMessage;
use App\Service\ActivityPub\Wrapper\UpdateWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MagazineUpdatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UpdateWrapper $updateWrapper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MagazineUpdatedEvent::class => 'onMagazineUpdated',
        ];
    }

    public function onMagazineUpdated(MagazineUpdatedEvent $event): void
    {
        $mag = $event->magazine;
        if (null === $mag->apId) {
            $activity = $this->updateWrapper->buildForActor($mag, $event->editedBy);
            $this->bus->dispatch(new GenericAnnounceMessage($mag->getId(), null, $event->editedBy->apDomain, $activity->uuid->toString(), null));
        } elseif (null !== $event->editedBy && null === $event->editedBy->apId) {
            $this->bus->dispatch(new UpdateMessage($mag->getId(), Magazine::class, $event->editedBy->getId()));
        }
    }
}

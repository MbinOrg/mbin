<?php

declare(strict_types=1);

namespace App\EventSubscriber\User;

use App\Entity\User;
use App\Event\User\UserEditedEvent;
use App\Message\ActivityPub\Outbox\UpdateMessage;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class UserEditedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserEditedEvent::class => 'onUserEdited',
        ];
    }

    public function onUserEdited(UserEditedEvent $event): void
    {
        $user = $this->userRepository->findOneBy(['id' => $event->userId]);
        if (null === $user->apId) {
            $this->bus->dispatch(new UpdateMessage($user->getId(), User::class, $user->getId()));
        }
    }
}

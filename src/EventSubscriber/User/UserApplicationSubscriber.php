<?php

declare(strict_types=1);

namespace App\EventSubscriber\User;

use App\Event\User\UserApplicationApprovedEvent;
use App\Event\User\UserApplicationRejectedEvent;
use App\Message\UserApplicationAnswerMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class UserApplicationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserApplicationRejectedEvent::class => 'onUserApplicationRejected',
            UserApplicationApprovedEvent::class => 'onUserApplicationApproved',
        ];
    }

    public function onUserApplicationApproved(UserApplicationApprovedEvent $event): void
    {
        $this->logger->debug('Got a UserApplicationApprovedEvent for {u}', ['u' => $event->user->username]);
        $this->bus->dispatch(new UserApplicationAnswerMessage($event->user->getId(), approved: true));
    }

    public function onUserApplicationRejected(UserApplicationRejectedEvent $event): void
    {
        $this->logger->debug('Got a UserApplicationRejectedEvent for {u}', ['u' => $event->user->username]);
        $this->bus->dispatch(new UserApplicationAnswerMessage($event->user->getId(), approved: false));
    }
}

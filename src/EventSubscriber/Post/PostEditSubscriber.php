<?php

declare(strict_types=1);

namespace App\EventSubscriber\Post;

use App\Event\Post\PostEditedEvent;
use App\Message\ActivityPub\Outbox\UpdateMessage;
use App\Message\LinkEmbedMessage;
use App\Message\Notification\PostEditedNotificationMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PostEditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostEditedEvent::class => 'onPostEdited',
        ];
    }

    public function onPostEdited(PostEditedEvent $event): void
    {
        $this->bus->dispatch(new PostEditedNotificationMessage($event->post->getId()));
        if ($event->post->body) {
            $this->bus->dispatch(new LinkEmbedMessage($event->post->body));
        }

        if (!$event->post->apId) {
            $this->bus->dispatch(new UpdateMessage($event->post->getId(), \get_class($event->post), $event->editedBy->getId()));
        }
    }
}

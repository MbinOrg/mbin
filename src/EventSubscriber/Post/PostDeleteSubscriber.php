<?php

declare(strict_types=1);

namespace App\EventSubscriber\Post;

use App\Entity\Post;
use App\Entity\User;
use App\Event\Post\PostBeforeDeletedEvent;
use App\Event\Post\PostBeforePurgeEvent;
use App\Event\Post\PostDeletedEvent;
use App\Message\ActivityPub\Outbox\DeleteMessage;
use App\Message\Notification\PostDeletedNotificationMessage;
use App\Repository\PostRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class PostDeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly PostRepository $postRepository,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostDeletedEvent::class => 'onPostDeleted',
            PostBeforePurgeEvent::class => 'onPostBeforePurge',
            PostBeforeDeletedEvent::class => 'onPostBeforeDelete',
        ];
    }

    public function onPostDeleted(PostDeletedEvent $event)
    {
        $this->bus->dispatch(new PostDeletedNotificationMessage($event->post->getId()));
    }

    public function onPostBeforePurge(PostBeforePurgeEvent $event): void
    {
        $event->post->magazine->postCount = $this->postRepository->countPostsByMagazine($event->post->magazine) - 1;
        $this->onPostBeforeDeleteImpl($event->user, $event->post);
    }

    public function onPostBeforeDelete(PostBeforeDeletedEvent $event): void
    {
        $this->onPostBeforeDeleteImpl($event->user, $event->post);
    }

    public function onPostBeforeDeleteImpl(?User $user, Post $post): void
    {
        $this->bus->dispatch(new PostDeletedNotificationMessage($post->getId()));

        if (!$post->apId || !$post->magazine->apId || (null !== $user && $post->magazine->userIsModerator($user))) {
            $activity = $this->deleteWrapper->adjustDeletePayload($user, $post);
            $payload = $this->activityJsonBuilder->buildActivityJson($activity);
            $this->bus->dispatch(new DeleteMessage($payload, $post->user->getId(), $post->magazine->getId()));
        }
    }
}

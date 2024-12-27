<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Magazine;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\FollowMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Wrapper\FollowResponseWrapper;
use App\Service\ActivityPubManager;
use App\Service\MagazineManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FollowHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly ActivityPubManager $activityPubManager,
        private readonly UserManager $userManager,
        private readonly MagazineManager $magazineManager,
        private readonly ApHttpClient $client,
        private readonly LoggerInterface $logger,
        private readonly FollowResponseWrapper $followResponseWrapper,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(FollowMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof FollowMessage)) {
            throw new \LogicException();
        }
        $this->logger->debug('got a FollowMessage: {message}', [$message]);
        $actor = $this->activityPubManager->findActorOrCreate($message->payload['actor']);
        // Check if actor is not empty
        if (!empty($actor)) {
            if ('Follow' === $message->payload['type']) {
                $object = $this->activityPubManager->findActorOrCreate($message->payload['object']);
                // Check if object is not empty
                if (!empty($object)) {
                    if ($object instanceof Magazine and null === $object->apId and 'random' === $object->name) {
                        $this->handleFollowRequest($message->payload, $object, isReject: true);
                    } else {
                        $this->handleFollow($object, $actor);

                        // @todo manually Accept
                        $this->handleFollowRequest($message->payload, $object);
                    }
                }

                return;
            }

            if (isset($message->payload['object'])) {
                switch ($message->payload['type']) {
                    case 'Undo':
                        $this->handleUnfollow(
                            $actor,
                            $this->activityPubManager->findActorOrCreate($message->payload['object']['object'])
                        );
                        break;
                    case 'Accept':
                        if ($actor instanceof User) {
                            $this->handleAccept(
                                $actor,
                                $this->activityPubManager->findActorOrCreate($message->payload['object']['actor'])
                            );
                        }
                        break;
                    case 'Reject':
                        $this->handleReject(
                            $actor,
                            $this->activityPubManager->findActorOrCreate($message->payload['object']['actor'])
                        );
                        break;
                    default:
                        break;
                }
            }
        }
    }

    private function handleFollow(User|Magazine $object, User $actor): void
    {
        match (true) {
            $object instanceof User => $this->userManager->follow($actor, $object),
            $object instanceof Magazine => $this->magazineManager->subscribe($object, $actor),
            default => throw new \LogicException(),
        };
    }

    private function handleFollowRequest(array $payload, User|Magazine $object, bool $isReject = false): void
    {
        $response = $this->followResponseWrapper->build(
            $payload['object'],
            $payload['actor'],
            $payload['id'],
            $isReject
        );

        $this->client->post($this->client->getInboxUrl($payload['actor']), $object, $response);
    }

    private function handleUnfollow(User $actor, User|Magazine|null $object): void
    {
        if (!empty($object)) {
            match (true) {
                $object instanceof User => $this->userManager->unfollow($actor, $object),
                $object instanceof Magazine => $this->magazineManager->unsubscribe($object, $actor),
                default => throw new \LogicException(),
            };
        }
    }

    private function handleAccept(User $actor, User|Magazine|null $object): void
    {
        if (!empty($object)) {
            if ($object instanceof User) {
                $this->userManager->acceptFollow($object, $actor);
            }

            //        if ($object instanceof Magazine) {
            //            $this->magazineManager->acceptFollow($actor, $object);
            //        }
        }
    }

    private function handleReject(User $actor, User|Magazine|null $object): void
    {
        if (!empty($object)) {
            match (true) {
                $object instanceof User => $this->userManager->rejectFollow($object, $actor),
                $object instanceof Magazine => $this->magazineManager->unsubscribe($object, $actor),
                default => throw new \LogicException(),
            };
        }
    }
}

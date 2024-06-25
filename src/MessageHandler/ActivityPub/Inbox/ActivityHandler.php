<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InboxForwardingException;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Message\ActivityPub\Inbox\AddMessage;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Inbox\DeleteMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Message\ActivityPub\Inbox\FlagMessage;
use App\Message\ActivityPub\Inbox\FollowMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\ActivityPub\Inbox\RemoveMessage;
use App\Message\ActivityPub\Inbox\UpdateMessage;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\SignatureValidator;
use App\Service\ActivityPubManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class ActivityHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SignatureValidator $signatureValidator,
        private SettingsManager $settingsManager,
        private MessageBusInterface $bus,
        private ActivityPubManager $manager,
        private ApHttpClient $apHttpClient,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ActivityMessage $message): void
    {
        $payload = @json_decode($message->payload, true);

        if ($message->request && $message->headers) {
            try {
                $this->signatureValidator->validate($message->request, $message->headers, $message->payload);
            } catch (InboxForwardingException $exception) {
                $this->logger->info("The message was forwarded by {receivedFrom}. Dispatching a new activity message '{origin}'", ['receivedFrom' => $exception->receivedFrom, 'origin' => $exception->realOrigin]);
                $body = $this->apHttpClient->getActivityObject($exception->realOrigin, false);
                $this->bus->dispatch(new ActivityMessage($body));

                return;
            }
        }

        if (isset($payload['payload'])) {
            $payload = $payload['payload'];
        }

        try {
            if (isset($payload['actor']) || isset($payload['attributedTo'])) {
                if (!$this->verifyInstanceDomain($payload['actor'] ?? $payload['attributedTo'])) {
                    return;
                }
                $user = $this->manager->findActorOrCreate($payload['actor'] ?? $payload['attributedTo']);
            } else {
                if (!$this->verifyInstanceDomain($payload['id'])) {
                    return;
                }
                $user = $this->manager->findActorOrCreate($payload['id']);
            }
        } catch (\Exception $e) {
            $this->logger->error('payload: '.json_encode($payload));

            return;
        }

        if ($user instanceof User && $user->isBanned) {
            return;
        }

        $this->handle($payload);
    }

    private function handle(?array $payload)
    {
        if (\is_null($payload)) {
            return;
        }

        if ('Announce' === $payload['type']) {
            // we check for an array here, because boosts are announces with an url (string) as the object
            if (\is_array($payload['object'])) {
                $actorObject = $this->manager->findActorOrCreate($payload['actor']);
                if ($actorObject instanceof Magazine && $actorObject->lastOriginUpdate < (new \DateTime())->modify('-3 hours')) {
                    if (isset($payload['object']['type']) && 'Create' === $payload['object']['type']) {
                        $actorObject->lastOriginUpdate = new \DateTime();
                        $this->entityManager->persist($actorObject);
                        $this->entityManager->flush();
                    }
                }

                $payload = $payload['object'];
                $actor = $payload['actor'] ?? $payload['attributedTo'] ?? null;
                if ($actor) {
                    $user = $this->manager->findActorOrCreate($actor);
                    if ($user instanceof User && null === $user->apId) {
                        // don't do anything if we get an announce activity for something a local user did (unless it's a boost, see comment above)
                        $this->logger->warning('ignoring this message because it announces an activity from a local user');

                        return;
                    }
                }
            }
        }

        $this->logger->debug('Got activity message of type {type}: {message}', ['type' => $payload['type'], 'message' => json_encode($payload)]);

        switch ($payload['type']) {
            case 'Create':
                $this->bus->dispatch(new CreateMessage($payload['object']));
                break;
            case 'Note':
            case 'Page':
            case 'Article':
            case 'Question':
                $this->bus->dispatch(new CreateMessage($payload));
                // no break
            case 'Announce':
                $this->bus->dispatch(new AnnounceMessage($payload));
                break;
            case 'Like':
                $this->bus->dispatch(new LikeMessage($payload));
                break;
            case 'Dislike':
                $this->bus->dispatch(new DislikeMessage($payload));
                break;
            case 'Follow':
                $this->bus->dispatch(new FollowMessage($payload));
                break;
            case 'Delete':
                $this->bus->dispatch(new DeleteMessage($payload));
                break;
            case 'Undo':
                $this->handleUndo($payload);
                break;
            case 'Accept':
            case 'Reject':
                $this->handleAcceptAndReject($payload);
                break;
            case 'Update':
                $this->bus->dispatch(new UpdateMessage($payload));
                break;
            case 'Add':
                $this->bus->dispatch(new AddMessage($payload));
                break;
            case 'Remove':
                $this->bus->dispatch(new RemoveMessage($payload));
                break;
            case 'Flag':
                $this->bus->dispatch(new FlagMessage($payload));
                break;
        }
    }

    private function handleUndo(array $payload): void
    {
        if (\is_array($payload['object'])) {
            $type = $payload['object']['type'];
        } else {
            $type = $payload['type'];
        }

        switch ($type) {
            case 'Follow':
                $this->bus->dispatch(new FollowMessage($payload));
                break;
            case 'Announce':
                $this->bus->dispatch(new AnnounceMessage($payload));
                break;
            case 'Like':
                $this->bus->dispatch(new LikeMessage($payload));
                break;
            case 'Dislike':
                $this->bus->dispatch(new DislikeMessage($payload));
                break;
        }
    }

    private function handleAcceptAndReject(array $payload): void
    {
        if (\is_array($payload['object'])) {
            $type = $payload['object']['type'];
        } else {
            $type = $payload['type'];
        }

        if ('Follow' === $type) {
            $this->bus->dispatch(new FollowMessage($payload));
        }
    }

    private function verifyInstanceDomain(?string $id): bool
    {
        if (!\is_null($id) && \in_array(
            str_replace('www.', '', parse_url($id, PHP_URL_HOST)),
            $this->settingsManager->get('KBIN_BANNED_INSTANCES') ?? []
        )) {
            return false;
        }

        return true;
    }
}

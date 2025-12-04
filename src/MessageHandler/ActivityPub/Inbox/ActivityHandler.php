<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Instance;
use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InboxForwardingException;
use App\Exception\InvalidUserPublicKeyException;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Message\ActivityPub\Inbox\AddMessage;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\BlockMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Inbox\DeleteMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Message\ActivityPub\Inbox\FlagMessage;
use App\Message\ActivityPub\Inbox\FollowMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\ActivityPub\Inbox\RemoveMessage;
use App\Message\ActivityPub\Inbox\UpdateMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\InstanceRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\SignatureValidator;
use App\Service\ActivityPubManager;
use App\Service\RemoteInstanceManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ActivityHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly SignatureValidator $signatureValidator,
        private readonly SettingsManager $settingsManager,
        private readonly MessageBusInterface $bus,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly InstanceRepository $instanceRepository,
        private readonly RemoteInstanceManager $remoteInstanceManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(ActivityMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof ActivityMessage)) {
            throw new \LogicException("ActivityHandler called, but is wasn\'t an ActivityMessage. Type: ".get_class($message));
        }

        $payload = @json_decode($message->payload, true);

        if (null === $payload) {
            $this->logger->warning('[ActivityHandler::doWork] Activity message from was empty or invalid JSON. Truncated content: {content}, ignoring it', [
                'content' => substr($message->payload ?? 'No payload provided', 0, 200),
            ]);
            throw new UnrecoverableMessageHandlingException('Activity message from was empty or invalid JSON');
        }

        if ($message->request && $message->headers) {
            try {
                $this->signatureValidator->validate($message->request, $message->headers, $message->payload);
            } catch (InboxForwardingException $exception) {
                $this->logger->info("[ActivityHandler::doWork] The message was forwarded by {receivedFrom}. Dispatching a new activity message '{origin}'", ['receivedFrom' => $exception->receivedFrom, 'origin' => $exception->realOrigin]);

                if (!$this->settingsManager->isBannedInstance($exception->realOrigin)) {
                    $body = $this->apHttpClient->getActivityObject($exception->realOrigin, false);
                    $this->bus->dispatch(new ActivityMessage($body));
                } else {
                    $this->logger->info('[ActivityHandler::doWork] The instance is banned, url: {url}', ['url' => $exception->realOrigin]);
                }

                return;
            } catch (InvalidUserPublicKeyException $exception) {
                $this->logger->warning("[ActivityHandler::doWork] Unable to extract public key for '{user}'.", ['user' => $exception->apProfileId]);

                return;
            }
        }

        if (null === $payload['id']) {
            $this->logger->warning('[ActivityHandler::doWork] Activity message has no id field which is required: {json}', ['json' => json_encode($message->payload)]);
            throw new UnrecoverableMessageHandlingException('Activity message has no id field');
        }

        $idHost = parse_url($payload['id'], PHP_URL_HOST);
        if ($idHost) {
            $instance = $this->instanceRepository->findOneBy(['domain' => $idHost]);
            if (!$instance) {
                $instance = new Instance($idHost);
                $instance->setLastSuccessfulReceive();
                $this->entityManager->persist($instance);
                $this->entityManager->flush();
            } else {
                $lastDate = $instance->getLastSuccessfulReceive();
                if ($lastDate < new \DateTimeImmutable('now - 5 minutes')) {
                    $instance->setLastSuccessfulReceive();
                    $this->entityManager->persist($instance);
                    $this->entityManager->flush();
                }
            }
            $this->remoteInstanceManager->updateInstance($instance);
        }

        if (isset($payload['payload'])) {
            $payload = $payload['payload'];
        }

        try {
            if (isset($payload['actor']) || isset($payload['attributedTo'])) {
                if (!$this->verifyInstanceDomain($payload['actor'] ?? $this->activityPubManager->getSingleActorFromAttributedTo($payload['attributedTo']))) {
                    return;
                }
                $user = $this->activityPubManager->findActorOrCreate($payload['actor'] ?? $this->activityPubManager->getSingleActorFromAttributedTo($payload['attributedTo']));
            } else {
                if (!$this->verifyInstanceDomain($payload['id'])) {
                    return;
                }
                $user = $this->activityPubManager->findActorOrCreate($payload['id']);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ActivityHandler::doWork] Payload: '.json_encode($payload));

            return;
        }

        if ($user instanceof User && $user->isBanned) {
            return;
        }

        if (null === $user) {
            $this->logger->warning('[ActivityHandler::doWork] Could not find an actor discarding ActivityMessage {m}', ['m' => $message->payload]);

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
                $actorObject = $this->activityPubManager->findActorOrCreate($payload['actor']);
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
                    $user = $this->activityPubManager->findActorOrCreate($actor);
                    if ($user instanceof User && null === $user->apId) {
                        // don't do anything if we get an announce activity for something a local user did (unless it's a boost, see comment above)
                        $this->logger->warning('[ActivityHandler::handle] Ignoring this message because it announces an activity from a local user');

                        return;
                    }
                }
            }
        }

        $this->logger->debug('[ActivityHandler::handle] Got activity message of type {type}: {message}', ['type' => $payload['type'], 'message' => json_encode($payload)]);

        switch ($payload['type']) {
            case 'Create':
                $this->bus->dispatch(new CreateMessage($payload['object'], fullCreatePayload: $payload));
                break;
            case 'Note':
            case 'Page':
            case 'Article':
            case 'Question':
            case 'Video':
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
            case 'Block':
                $this->bus->dispatch(new BlockMessage($payload));
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
            case 'Block':
                $this->bus->dispatch(new BlockMessage($payload));
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
            $this->instanceRepository->getBannedInstanceUrls()
        )) {
            return false;
        }

        return true;
    }
}

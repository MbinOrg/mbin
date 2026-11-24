<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Exception\EntityNotFoundException;
use App\Exception\EntryLockedException;
use App\Exception\InstanceBannedException;
use App\Exception\PostLockedException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Exception\UserDeletedException;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use App\Service\SettingsManager;
use App\Utils\JsonldUtils;
use App\Utils\LogContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ChainActivityHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClientInterface $client,
        private readonly MessageBusInterface $bus,
        private readonly ApActivityRepository $repository,
        private readonly Note $note,
        private readonly Page $page,
        private readonly SettingsManager $settingsManager,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(ChainActivityMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof ChainActivityMessage)) {
            throw new \LogicException("ChainActivityHandler called, but is wasn\'t a ChainActivityMessage. Type: ".\get_class($message));
        }
        if (!$message->chain || 0 === \sizeof($message->chain)) {
            return;
        }
        $validObjectTypes = ['Page', 'Note', 'Article', 'Question', 'Video'];
        $object = $message->chain[0];
        $context = [
            'object_id' => LogContext::identifier($object['id'] ?? null),
            'object_type' => LogContext::type($object['type'] ?? null) ?? 'unknown',
        ];
        if (!\in_array($object['type'], $validObjectTypes, true)) {
            $this->logger->error('[ChainActivityHandler::doWork] Unsupported federation dependency type {object_type}', $context);

            return;
        }
        $failure = null;
        try {
            $entity = $this->retrieveObject($object['id'], $failure);
        } catch (InstanceBannedException) {
            $entity = null;
        }

        if (!$entity) {
            // Report the deepest failure once for the root activity, even when several parents failed to resolve.
            $reason = $failure['reason'] ?? 'Dependency could not be resolved';
            $level = $failure['level'] ?? 'error';
            $dependencyId = $failure['dependency_id'] ?? null;
            if (null !== $dependencyId) {
                $reason .= ' ({dependency_id})';
            }
            if (null !== $context['object_id'] && $context['object_id'] !== $dependencyId) {
                $reason .= ' for {object_id}';
            }
            if (isset($failure['exception_type'])) {
                $reason .= ' ({exception_type})';
            }
            $this->logger->log($level, '[ChainActivityHandler::doWork] '.$reason, $context + [
                'dependency_id' => $dependencyId,
                'exception_type' => $failure['exception_type'] ?? null,
            ]);

            return;
        }

        if ($message->announce) {
            $this->bus->dispatch(new AnnounceMessage($message->announce));
        }

        if ($message->like) {
            $this->bus->dispatch(new LikeMessage($message->like));
        }

        if ($message->dislike) {
            $this->bus->dispatch(new DislikeMessage($message->dislike));
        }
    }

    /**
     * @param array{reason: string, level: string, dependency_id: ?string, exception_type?: class-string}|null $failure
     */
    private function retrieveObject(string $apUrl, ?array &$failure): Entry|EntryComment|Post|PostComment|null
    {
        $context = ['dependency_id' => LogContext::identifier($apUrl)];
        if ($this->settingsManager->isBannedInstance($apUrl)) {
            $failure = $context + ['reason' => 'Federation dependency instance is banned', 'level' => 'info'];

            throw new InstanceBannedException();
        }

        try {
            $object = $this->client->getActivityObject($apUrl);
            if (!\is_array($object) || !$object) {
                $failure = $context + ['reason' => 'Federation dependency is missing or invalid', 'level' => 'error'];

                return null;
            }

            if (\array_key_exists('inReplyTo', $object) && null !== $object['inReplyTo']) {
                $parentUrl = JsonldUtils::getApId($object['inReplyTo']);
                $meta = $this->repository->findByObjectId($parentUrl);
                if (!$meta) {
                    $this->retrieveObject($parentUrl, $failure);
                }
                $meta = $this->repository->findByObjectId($parentUrl);
                if (!$meta) {
                    $failure ??= [
                        'dependency_id' => LogContext::identifier($parentUrl),
                        'reason' => 'Federation parent dependency is missing',
                        'level' => 'error',
                    ];

                    return null;
                }
                $failure = null;
            }

            switch ($object['type']) {
                case 'Question':
                case 'Note':
                    return $this->note->create($object);
                case 'Page':
                case 'Article':
                case 'Video':
                    return $this->page->create($object);
                default:
                    $failure = $context + ['reason' => 'Unsupported federation dependency type', 'level' => 'error'];
            }
        } catch (UserBannedException|UserDeletedException|TagBannedException|EntryLockedException|PostLockedException $e) {
            $failure = $context + ['reason' => 'Federation dependency rejected by moderation', 'level' => 'info', 'exception_type' => $e::class];
        } catch (InstanceBannedException $e) {
            $failure ??= $context + ['reason' => 'Federation dependency instance is banned', 'level' => 'info', 'exception_type' => $e::class];
        } catch (EntityNotFoundException $e) {
            $failure = $context + ['reason' => 'Could not load federation dependency', 'level' => 'error', 'exception_type' => $e::class];
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Exception\InstanceBannedException;
use App\Exception\PostingRestrictedException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Exception\UserDeletedException;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Outbox\AnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use App\Service\ActivityPubManager;
use App\Service\MessageManager;
use App\Utils\UrlUtils;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler]
class CreateHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly Note $note,
        private readonly Page $page,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly MessageManager $messageManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApActivityRepository $repository,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    /**
     * @throws \Exception
     */
    public function __invoke(CreateMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof CreateMessage)) {
            throw new \LogicException();
        }
        $object = $message->payload;
        $stickyIt = $message->stickyIt;
        $this->logger->debug('Got a CreateMessage of type {t}, {m}', ['t' => $message->payload['type'], 'm' => $message->payload]);
        $entryTypes = ['Page', 'Article', 'Video'];
        $postTypes = ['Question', 'Note'];

        try {
            if ('ChatMessage' === $object['type']) {
                $this->handlePrivateMessage($object);
            } elseif (\in_array($object['type'], $postTypes)) {
                $this->handleChain($object, $stickyIt);
                if (method_exists($this->cache, 'invalidateTags')) {
                    // clear markdown renders that are tagged with the id of the post
                    $tag = UrlUtils::getCacheKeyForMarkdownUrl($object['id']);
                    $this->cache->invalidateTags([$tag]);
                    $this->logger->debug('cleared cached items with tag {t}', ['t' => $tag]);
                }
            } elseif (\in_array($object['type'], $entryTypes)) {
                $this->handlePage($object, $stickyIt);
                if (method_exists($this->cache, 'invalidateTags')) {
                    // clear markdown renders that are tagged with the id of the entry
                    $tag = UrlUtils::getCacheKeyForMarkdownUrl($object['id']);
                    $this->cache->invalidateTags([$tag]);
                    $this->logger->debug('cleared cached items with tag {t}', ['t' => $tag]);
                }
            }
        } catch (UserBannedException) {
            $this->logger->info('[CreateHandler::handleModeratorAdd] Did not create the post, because the user is banned');
        } catch (UserDeletedException) {
            $this->logger->info('[CreateHandler::handleModeratorAdd] Did not create the post, because the user is deleted');
        } catch (TagBannedException) {
            $this->logger->info('[CreateHandler::handleModeratorAdd] Did not create the post, because one of the used tags is banned');
        } catch (PostingRestrictedException $e) {
            if ($e->actor instanceof User) {
                $username = $e->actor->getUsername();
            } else {
                $username = $e->actor->name;
            }
            $this->logger->info('[CreateHandler::handleModeratorAdd] Did not create the post, because the magazine {m} restricts posting to mods and {u} is not a mod', ['m' => $e->magazine, 'u' => $username]);
        } catch (InstanceBannedException $e) {
            $this->logger->info('[CreateHandler::handleModeratorAdd] Did not create the post, because the user\'s instance is banned');
        }
    }

    /**
     * @throws TagBannedException
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws InstanceBannedException
     */
    private function handleChain(array $object, bool $stickyIt): void
    {
        if (isset($object['inReplyTo']) && $object['inReplyTo']) {
            $existed = $this->repository->findByObjectId($object['inReplyTo']);
            if (!$existed) {
                $this->bus->dispatch(new ChainActivityMessage([$object]));

                return;
            }
        }

        $note = $this->note->create($object, stickyIt: $stickyIt);
        if ($note instanceof EntryComment || $note instanceof Post || $note instanceof PostComment) {
            if (null !== $note->apId and null === $note->magazine->apId and 'random' !== $note->magazine->name) {
                // local magazine, but remote post. Random magazine is ignored, as it should not be federated at all
                $this->bus->dispatch(new AnnounceMessage(null, $note->magazine->getId(), $note->getId(), \get_class($note)));
            }
        }
    }

    /**
     * @throws \Exception
     * @throws UserBannedException
     * @throws UserDeletedException
     * @throws TagBannedException
     * @throws PostingRestrictedException
     * @throws InstanceBannedException
     */
    private function handlePage(array $object, bool $stickyIt): void
    {
        $page = $this->page->create($object, stickyIt: $stickyIt);
        if ($page instanceof Entry) {
            if (null !== $page->apId and null === $page->magazine->apId and 'random' !== $page->magazine->name) {
                // local magazine, but remote post. Random magazine is ignored, as it should not be federated at all
                $this->bus->dispatch(new AnnounceMessage(null, $page->magazine->getId(), $page->getId(), \get_class($page)));
            }
        }
    }

    private function handlePrivateMessage(array $object): void
    {
        $this->messageManager->createMessage($object);
    }

    private function handlePrivateMentions(): void
    {
        // TODO implement private mentions
    }
}

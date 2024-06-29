<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\CreateMessage;
use App\Message\ActivityPub\Outbox\AnnounceMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CreateHandler
{
    private array $object;
    private bool $stickyIt;

    public function __construct(
        private readonly Note $note,
        private readonly Page $page,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
        private readonly ApActivityRepository $repository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(CreateMessage $message): void
    {
        $this->object = $message->payload;
        $this->stickyIt = $message->stickyIt;
        $this->logger->debug('Got a CreateMessage of type {t}', [$message->payload['type'], $message->payload]);
        $entryTypes = ['Page', 'Article', 'Video'];
        $postTypes = ['Question', 'Note'];

        try {
            if (\in_array($this->object['type'], $postTypes)) {
                $this->handleChain();
            }

            if (\in_array($this->object['type'], $entryTypes)) {
                $this->handlePage();
            }
        } catch (UserBannedException) {
            $this->logger->info('Did not create the post, because the user is banned');
        } catch (TagBannedException) {
            $this->logger->info('Did not create the post, because one of the used tags is banned');
        }
    }

    private function handleChain(): void
    {
        if (isset($this->object['inReplyTo']) && $this->object['inReplyTo']) {
            $existed = $this->repository->findByObjectId($this->object['inReplyTo']);
            if (!$existed) {
                $this->bus->dispatch(new ChainActivityMessage([$this->object]));

                return;
            }
        }

        $note = $this->note->create($this->object, stickyIt: $this->stickyIt);
        // TODO atm post and post comment are not announced, because of the micro blog spam towards lemmy. If we implement magazine name as hashtag to be optional than this may be reverted
        if ($note instanceof EntryComment /* or $note instanceof Post or $note instanceof PostComment */) {
            if (null !== $note->apId and null === $note->magazine->apId and 'random' !== $note->magazine->name) {
                // local magazine, but remote post. Random magazine is ignored, as it should not be federated at all
                $this->bus->dispatch(new AnnounceMessage(null, $note->magazine->getId(), $note->getId(), \get_class($note)));
            }
        }
    }

    /**
     * @throws \Exception
     * @throws UserBannedException
     * @throws TagBannedException
     */
    private function handlePage(): void
    {
        $page = $this->page->create($this->object, stickyIt: $this->stickyIt);
        if ($page instanceof Entry) {
            if (null !== $page->apId and null === $page->magazine->apId and 'random' !== $page->magazine->name) {
                // local magazine, but remote post. Random magazine is ignored, as it should not be federated at all
                $this->bus->dispatch(new AnnounceMessage(null, $page->magazine->getId(), $page->getId(), \get_class($page)));
            }
        }
    }
}

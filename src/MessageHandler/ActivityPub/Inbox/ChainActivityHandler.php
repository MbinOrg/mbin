<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Exception\EntityNotFoundException;
use App\Exception\TagBannedException;
use App\Exception\UserBannedException;
use App\Message\ActivityPub\Inbox\AnnounceMessage;
use App\Message\ActivityPub\Inbox\ChainActivityMessage;
use App\Message\ActivityPub\Inbox\DislikeMessage;
use App\Message\ActivityPub\Inbox\LikeMessage;
use App\Repository\ApActivityRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\Note;
use App\Service\ActivityPub\Page;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ChainActivityHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ApHttpClient $client,
        private readonly MessageBusInterface $bus,
        private readonly ApActivityRepository $repository,
        private readonly Note $note,
        private readonly Page $page
    ) {
    }

    public function __invoke(ChainActivityMessage $message): void
    {
        $this->entityManager->wrapInTransaction(fn () => $this->doWork($message));
    }

    public function doWork(ChainActivityMessage $message): void
    {
        $this->logger->debug('Got chain activity message: {m}', ['m' => $message]);
        if (!$message->chain || 0 === \sizeof($message->chain)) {
            return;
        }
        $validObjectTypes = ['Page', 'Note', 'Article', 'Question', 'Video'];
        $object = $message->chain[0];
        if (!\in_array($object['type'], $validObjectTypes)) {
            $this->logger->error('cannot get the dependencies of the object, its type {t} is not one we can handle. {m]', ['t' => $object['type'], 'm' => $message]);

            return;
        }

        $entity = $this->retrieveObject($object['id']);

        if (!$entity) {
            $this->logger->error('could not retrieve all the dependencies of {o}', ['o' => $object]);

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
     * @throws \Exception if there was an unexpected exception
     */
    private function retrieveObject(string $apUrl): Entry|EntryComment|Post|PostComment|null
    {
        try {
            $object = $this->client->getActivityObject($apUrl);
            if (!$object) {
                $this->logger->warning('Got an empty object for {url}', ['url' => $apUrl]);

                return null;
            }
            if (!\is_array($object)) {
                $this->logger->warning("Didn't get an array for {url}. Got '{val}' instead, exiting", ['url' => $apUrl, 'val' => $object]);

                return null;
            }

            if (\array_key_exists('inReplyTo', $object) && null !== $object['inReplyTo']) {
                $parentUrl = \is_string($object['inReplyTo']) ? $object['inReplyTo'] : $object['inReplyTo']['id'];
                $meta = $this->repository->findByObjectId($parentUrl);
                if (!$meta) {
                    $this->retrieveObject($parentUrl);
                }
                $meta = $this->repository->findByObjectId($parentUrl);
                if (!$meta) {
                    $this->logger->warning('fetching the parent object ({parent}) did not work for {url}, aborting', ['parent' => $parentUrl, 'url' => $apUrl]);

                    return null;
                }
            }

            switch ($object['type']) {
                case 'Question':
                case 'Note':
                    $this->logger->debug('creating note {o}', ['o' => $object]);

                    return $this->note->create($object);
                case 'Page':
                case 'Article':
                case 'Video':
                    $this->logger->debug('creating page {o}', ['o' => $object]);

                    return $this->page->create($object);
                default:
                    $this->logger->warning('Could not create an object from type {t} on {url}: {o}', ['t' => $object['type'], 'url' => $apUrl, 'o' => $object]);
            }
        } catch (UserBannedException) {
            $this->logger->error('the user is banned, url: {url}', ['url' => $apUrl]);
        } catch (TagBannedException) {
            $this->logger->error('one of the used tags is banned, url: {url}', ['url' => $apUrl]);
        } catch (EntityNotFoundException $e) {
            $this->logger->error('There was an exception while getting {url}: {ex} - {m}. {o}', ['url' => $apUrl, 'ex' => \get_class($e), 'm' => $e->getMessage(), 'o' => $e]);
        }

        return null;
    }
}

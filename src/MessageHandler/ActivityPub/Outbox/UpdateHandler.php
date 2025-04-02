<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\UpdateMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\Wrapper\UpdateWrapper;
use App\Service\ActivityPubManager;
use App\Service\DeliverManager;
use App\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $activityPubManager,
        private readonly SettingsManager $settingsManager,
        private readonly DeliverManager $deliverManager,
        private readonly UpdateWrapper $updateWrapper,
        private readonly KernelInterface $kernel,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(UpdateMessage $message): void
    {
        if (!$this->settingsManager->get('KBIN_FEDERATION_ENABLED')) {
            return;
        }
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UpdateMessage)) {
            throw new \LogicException();
        }

        $entity = $this->entityManager->getRepository($message->type)->find($message->id);
        $editedByUser = null;
        if ($message->editedByUserId) {
            $editedByUser = $this->userRepository->findOneBy(['id' => $message->editedByUserId]);
        }

        if ($entity instanceof ActivityPubActivityInterface) {
            $activity = $this->updateWrapper->buildForActivity($entity, $editedByUser);

            if ($entity instanceof Entry || $entity instanceof EntryComment || $entity instanceof Post || $entity instanceof PostComment) {
                if ('random' === $entity->magazine->name) {
                    // do not federate the random magazine
                    return;
                }

                $inboxes = array_filter(array_unique(array_merge(
                    $this->userRepository->findAudience($entity->user),
                    $this->activityPubManager->createInboxesFromCC($activity, $entity->user),
                    $this->magazineRepository->findAudience($entity->magazine)
                )));
            } elseif ($entity instanceof Message) {
                if (null === $message->editedByUserId) {
                    throw new \LogicException('a message has to be edited by someone');
                }
                $inboxes = array_unique(array_map(fn (User $u) => $u->apInboxUrl, $entity->thread->getOtherParticipants($message->editedByUserId)));
            } else {
                throw new \LogicException('unknown activity type: '.\get_class($entity));
            }
        } elseif ($entity instanceof ActivityPubActorInterface) {
            $activity = $this->updateWrapper->buildForActor($entity, $editedByUser);
            if ($entity instanceof User) {
                $inboxes = $this->userRepository->findAudience($entity);
                $this->logger->debug('[UpdateHandler::doWork] sending update user activity for user {u} to {i}', ['u' => $entity->username, 'i' => join(', ', $inboxes)]);
            } elseif ($entity instanceof Magazine) {
                if ('random' === $entity->name) {
                    // do not federate the random magazine
                    return;
                }

                if (null === $entity->apId) {
                    $inboxes = $this->magazineRepository->findAudience($entity);
                    if (null !== $editedByUser) {
                        $inboxes = array_filter($inboxes, fn (string $domain) => $editedByUser->apInboxUrl !== $domain);
                    }
                } else {
                    $inboxes = [$entity->apInboxUrl];
                }
            } else {
                throw new \LogicException('Unknown actor type: '.\get_class($entity));
            }
        } else {
            throw new \LogicException('Unknown activity type: '.\get_class($entity));
        }

        $this->deliverManager->deliver($inboxes, $activity);
    }
}

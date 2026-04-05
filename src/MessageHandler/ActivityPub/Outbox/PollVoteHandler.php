<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Outbox;

use App\Entity\PollVote;
use App\Message\ActivityPub\Outbox\PollVoteMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\CreateWrapper;
use App\Service\DeliverManager;
use App\Service\PollManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class PollVoteHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CreateWrapper $createWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly DeliverManager $deliverManager,
        private readonly PollManager $pollManager,
        private readonly LoggerInterface $logger,
        KernelInterface $kernel,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(PollVoteMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof PollVoteMessage) {
            throw new \LogicException();
        }

        $vote = $this->entityManager->getRepository(PollVote::class)->find(Uuid::fromString($message->voteUuid));
        if (!$vote->poll->isRemote) {
            $this->logger->info('The poll {p} is not remote, so we do not have to send anything', ['p' => $message->voteUuid]);

            return;
        } elseif (null !== $vote->voter->apId) {
            $this->logger->info('The voter of poll {p} is not a local user, so we do not have to send anything', ['p' => $message->voteUuid]);

            return;
        }

        $content = $this->pollManager->getContentOfPoll($vote->poll);
        if (null === $content) {
            $this->logger->warning('Could not find the content the poll {p} belongs to', ['p' => $vote->poll]);

            return;
        }

        if (null === $content->user->apId) {
            $this->logger->info('The content of poll {p} is not a local, so we do not have to send anything', ['p' => $message->voteUuid]);

            return;
        }

        $activity = $this->createWrapper->build($vote);
        $json = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->deliverManager->deliver([$content->user->apInboxUrl], $json);
    }
}

<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub;

use App\Message\ActivityPub\UpdateActorMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPubManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateActorHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityPubManager $manager,
        private readonly ApHttpClient $apHttpClient,
        private readonly LockFactory $lockFactory,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(UpdateActorMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UpdateActorMessage)) {
            throw new \LogicException();
        }
        $actorUrl = $message->actorUrl;
        $lock = $this->lockFactory->createLock('update_actor_'.hash('sha256', $actorUrl), 60);

        if (!$lock->acquire()) {
            $this->logger->debug(
                'not updating actor at {url}: ongoing actor update is already in progress',
                ['url' => $actorUrl]
            );

            return;
        }

        $actor = $this->userRepository->findOneBy(['apProfileId' => $actorUrl])
            ?? $this->magazineRepository->findOneBy(['apProfileId' => $actorUrl]);

        if ($actor) {
            if ($message->force) {
                $this->apHttpClient->invalidateActorObjectCache($actorUrl);
            }
            if ($message->force || $actor->apFetchedAt < (new \DateTime())->modify('-1 hour')) {
                $this->manager->updateActor($actorUrl);
            } else {
                $this->logger->debug('not updating actor {url}: last updated is recent: {fetched}', [
                    'url' => $actorUrl,
                    'fetched' => $actor->apFetchedAt,
                ]);
            }
        }

        $lock->release();
    }
}

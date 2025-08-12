<?php

declare(strict_types=1);

namespace App\MessageHandler\ActivityPub\Inbox;

use App\DTO\MagazineBanDto;
use App\Entity\Magazine;
use App\Entity\MagazineBan;
use App\Entity\User;
use App\Exception\UserCannotBeBanned;
use App\Message\ActivityPub\Inbox\BlockMessage;
use App\Message\ActivityPub\Outbox\GenericAnnounceMessage;
use App\Message\Contracts\MessageInterface;
use App\MessageHandler\MbinMessageHandler;
use App\Repository\ActivityRepository;
use App\Repository\MagazineBanRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPubManager;
use App\Service\MagazineManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class BlockHandler extends MbinMessageHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        KernelInterface $kernel,
        private readonly MagazineBanRepository $magazineBanRepository,
        private readonly ActivityPubManager $activityPubManager,
        private readonly ApHttpClientInterface $apHttpClient,
        private readonly MagazineManager $magazineManager,
        private readonly UserManager $userManager,
        private readonly ActivityRepository $activityRepository,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($entityManager, $kernel);
    }

    public function __invoke(BlockMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!$message instanceof BlockMessage) {
            throw new \LogicException();
        }

        if (!isset($message->payload['id']) || !isset($message->payload['actor']) || !isset($message->payload['object'])) {
            throw new UnrecoverableMessageHandlingException('Malformed block activity');
        }

        $this->logger->debug('Got block message: {m}', ['m' => $message->payload]);

        $isUndo = 'Undo' === $message->payload['type'];
        $payload = $isUndo ? $message->payload['object'] : $message->payload;

        if (\is_string($payload) && filter_var($payload, FILTER_VALIDATE_URL)) {
            $payload = $this->apHttpClient->getActivityObject($payload);
        }

        if (!isset($payload['id']) || !isset($payload['actor']) || !isset($payload['object']) || !isset($payload['target'])) {
            throw new UnrecoverableMessageHandlingException('Malformed block activity');
        }

        $actor = $this->activityPubManager->findActorOrCreate($payload['actor']);
        if (null === $actor) {
            throw new \Exception("Unable to find user '{$payload['actor']}'");
        }

        $bannedUser = $this->activityPubManager->findActorOrCreate($payload['object']);
        if (null === $bannedUser) {
            throw new \Exception("Could not find user '{$payload['object']}'");
        }
        if (!$bannedUser instanceof User) {
            throw new UnrecoverableMessageHandlingException('The object has to be a user');
        }

        try {
            $target = $this->activityPubManager->findActorOrCreate($payload['target']);
        } catch (\Exception $e) {
            if (parse_url($payload['target'], PHP_URL_HOST) === $bannedUser->apDomain) {
                // if the host part of the url is the same as the users it is probably the instance actor -> ban the user completely
                $target = null;
            } else {
                throw $e;
            }
        }

        $reason = $payload['summary'] ?? '';
        $expireDate = null;
        if (isset($payload['expires'])) {
            $expireDate = new \DateTimeImmutable($payload['expires']);
        }

        if (null === $target || ($target instanceof User && 'Application' === $target->type)) {
            $this->handleInstanceBan($bannedUser, $actor, $reason, $isUndo);
        } else {
            $this->handleMagazineBan($message->payload, $bannedUser, $actor, $target, $reason, $expireDate, $isUndo);
        }
    }

    private function handleInstanceBan(User $bannedUser, User $actor, string $reason, bool $isUndo): void
    {
        if ($bannedUser->apDomain !== $actor->apDomain) {
            throw new UnrecoverableMessageHandlingException("Only a user of the same instance can instance ban another user and the domains of the banned $bannedUser->username and the actor $actor->username do not match");
        }
        if ($isUndo) {
            $this->userManager->unban($bannedUser, $actor, $reason);
            $this->logger->info('[BlockHandler::handleInstanceBan] {a} is unbanning {u} instance wide', ['a' => $actor->username, 'u' => $bannedUser->username]);
        } else {
            $this->userManager->ban($bannedUser, $actor, $reason);
            $this->logger->info('[BlockHandler::handleInstanceBan] {a} is banning {u} instance wide', ['a' => $actor->username, 'u' => $bannedUser->username]);
        }
    }

    private function handleMagazineBan(array $payload, User $bannedUser, User $actor, Magazine $target, string $reason, ?\DateTimeImmutable $expireDate, bool $isUndo): void
    {
        if (!$target->hasSameHostAsUser($actor) && !$target->userIsModerator($actor)) {
            throw new UnrecoverableMessageHandlingException("The user $actor->username is neither from the same instance as the magazine $target->name nor a moderator in it and is therefore not allowed to ban $bannedUser->username");
        }

        $existingBan = $this->magazineBanRepository->findOneBy(['magazine' => $target, 'user' => $bannedUser]);
        if (null === $existingBan) {
            $this->logger->debug('it is a magazine ban and we do not have an existing one');
            if ($isUndo) {
                // nothing has to be done, the user is not banned
                $this->logger->debug("We didn't know that {u} was banned from {m}, so we do not have to undo it", ['u' => $bannedUser->username, 'm' => $target->name]);

                return;
            } else {
                $ban = $this->banImpl($reason, $expireDate, $target, $bannedUser, $actor);

                if (null === $target->apId) {
                    // local magazine and the user is allowed to ban users -> announce it
                    $this->announceBan($payload, $target, $actor, $ban);
                }
            }
        } else {
            $this->logger->debug('it is a magazine ban and we do have an existing one');
            if ($isUndo) {
                $ban = $this->magazineManager->unban($target, $bannedUser);
                $this->logger->info("[BlockHandler::handleMagazineBan] {a} is unbanning {u} from magazine {m}. Reason: '{r}'", ['a' => $actor->username, 'u' => $bannedUser->username, 'm' => $target->name, 'r' => $reason]);
            } else {
                $ban = $this->banImpl($reason, $expireDate, $target, $bannedUser, $actor);
            }

            if (null === $target->apId) {
                // local magazine and the user is allowed to ban users -> announce it
                $this->announceBan($payload, $target, $actor, $ban);
            }
        }
    }

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    private function announceBan(array $payload, Magazine $target, User $actor, MagazineBan $ban): void
    {
        $activityToAnnounce = $payload;
        unset($activityToAnnounce['@context']);
        $activity = $this->activityRepository->createForRemoteActivity($payload, $ban);
        $activity->audience = $target;
        $activity->setActor($actor);

        $this->bus->dispatch(new GenericAnnounceMessage($target->getId(), null, $actor->apInboxUrl, $activity->uuid->toString(), null));
    }

    private function banImpl(string $reason, ?\DateTimeImmutable $expireDate, Magazine $target, User $bannedUser, User $actor): MagazineBan
    {
        $dto = new MagazineBanDto();
        $dto->reason = $reason;
        $dto->expiredAt = $expireDate;
        try {
            $ban = $this->magazineManager->ban($target, $bannedUser, $actor, $dto);
            $this->logger->info("[BlockHandler::handleMagazineBan] {a} is banning {u} from magazine {m}. Reason: '{r}'", ['a' => $actor->username, 'u' => $bannedUser->username, 'm' => $target->name, 'r' => $reason]);
        } catch (UserCannotBeBanned) {
            throw new UnrecoverableMessageHandlingException("$bannedUser->username is either an admin or a moderator of $target->name and can therefor not be banned from it");
        }

        return $ban;
    }
}

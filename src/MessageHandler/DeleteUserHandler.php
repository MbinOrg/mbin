<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\UserDto;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\Contracts\MessageInterface;
use App\Message\DeleteUserMessage;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use App\Service\ImageManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DeleteUserHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
        private readonly UserManager $userManager,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct($this->entityManager);
    }

    public function __invoke(DeleteUserMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof DeleteUserMessage)) {
            throw new \LogicException();
        }
        /** @var ?User */
        $user = $this->entityManager->getRepository(User::class)->find($message->id);

        if (!$user) {
            $this->logger->warning('User not found for deletion', ['id' => $message->id]);

            return;
        }

        if ($user->isDeleted && null === $user->markedForDeletionAt) {
            $this->logger->notice('User was already deleted', ['id' => $message->id, 'user' => $user->username]);

            return;
        }

        $isLocal = null === $user->apId;

        $privateKey = $user->getPrivateKey();
        $publicKey = $user->getPublicKey();

        $inboxes = $this->getInboxes($user);

        // note: email cannot be null. For remote accounts email is set to their 'handle@domain.tld' who knows why...
        $userDto = UserDto::create($user->username, email: $user->username, createdAt: $user->createdAt);
        $userDto->plainPassword = ''.time();
        if (!$isLocal) {
            $userDto->apId = $user->apId;
            $userDto->apProfileId = $user->apProfileId;
        }

        try {
            $this->userManager->detachAvatar($user);
        } catch (\Exception|\Error $e) {
            $this->logger->error("couldn't delete the avatar of {user} at '{path}': {message}", [
                'user' => $user->username,
                'path' => $user->avatar?->filePath,
                'message' => \get_class($e).': '.$e->getMessage(),
                'exception' => $e,
            ]);
        }
        try {
            $this->userManager->detachCover($user);
        } catch (\Exception|\Error $e) {
            $this->logger->error("couldn't delete the cover of {user} at '{path}': {message}", [
                'user' => $user->username,
                'path' => $user->cover?->filePath,
                'message' => \get_class($e).': '.$e->getMessage(),
                'exception' => $e,
            ]);
        }
        $filePathsOfUser = $this->userManager->getAllImageFilePathsOfUser($user);
        foreach ($filePathsOfUser as $path) {
            try {
                $this->imageManager->remove($path);
            } catch (\Exception|\Error $e) {
                $this->logger->error("couldn't delete image of {user} at '{path}': {message}", [
                    'user' => $user->username,
                    'path' => $path,
                    'message' => \get_class($e).': '.$e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        $this->entityManager->beginTransaction();

        // delete the original user, so all the content is cascade deleted
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        // recreate a user with the same name, so this handle is blocked
        $user = $this->userManager->create($userDto, verifyUserEmail: false, rateLimit: false);
        $user->isDeleted = true;
        $user->markedForDeletionAt = null;
        $user->isVerified = false;

        if ($isLocal) {
            $user->privateKey = $privateKey;
            $user->publicKey = $publicKey;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($isLocal) {
            $this->sendDeleteMessages($inboxes, $user);
        }

        $this->entityManager->commit();
    }

    private function getInboxes(User $user): array
    {
        return array_unique(array_filter($this->userManager->getAllInboxesOfInteractions($user)));
    }

    private function sendDeleteMessages(array $targetInboxes, User $deletedUser): void
    {
        if (null !== $deletedUser->apId) {
            return;
        }

        $message = $this->deleteWrapper->buildForUser($deletedUser);

        foreach ($targetInboxes as $inbox) {
            $this->bus->dispatch(new DeliverMessage($inbox, $message));
        }
    }
}

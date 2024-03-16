<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\DTO\UserDto;
use App\Entity\User;
use App\Message\ActivityPub\Outbox\DeliverMessage;
use App\Message\DeleteUserMessage;
use App\Service\ActivityPub\Wrapper\DeleteWrapper;
use App\Service\ImageManager;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DeleteUserHandler
{
    private ?User $user;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
        private readonly UserManager $userManager,
        private readonly DeleteWrapper $deleteWrapper,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(DeleteUserMessage $message): void
    {
        $this->user = $this->entityManager
            ->getRepository(User::class)
            ->find($message->id);

        if (!$this->user) {
            throw new UnrecoverableMessageHandlingException('User not found');
        } elseif ($this->user->isDeleted && null === $this->user->markedForDeletionAt) {
            throw new UnrecoverableMessageHandlingException('User already deleted');
        }

        $isLocal = null === $this->user->apId;

        $privateKey = $this->user->getPrivateKey();
        $publicKey = $this->user->getPublicKey();

        $inboxes = $this->getInboxes();

        // note: email cannot be null. For remote accounts email is set to their 'handle@domain.tld' who knows why...
        $userDto = UserDto::create($this->user->username, email: $this->user->username, createdAt: $this->user->createdAt);
        $userDto->plainPassword = ''.time();

        try {
            $this->userManager->detachAvatar($this->user);
        } catch (\Exception|\Error $e) {
            $this->logger->error("couldn't delete the avatar of {user} at '{path}': {message}", ['user' => $this->user->username, 'path' => $this->user->avatar?->filePath, 'message' => \get_class($e).': '.$e->getMessage()]);
        }
        try {
            $this->userManager->detachCover($this->user);
        } catch (\Exception|\Error $e) {
            $this->logger->error("couldn't delete the cover of {user} at '{path}': {message}", ['user' => $this->user->username, 'path' => $this->user->cover?->filePath, 'message' => \get_class($e).': '.$e->getMessage()]);
        }
        $filePathsOfUser = $this->userManager->getAllImageFilePathsOfUser($this->user);
        foreach ($filePathsOfUser as $path) {
            try {
                $this->imageManager->remove($path);
            } catch (\Exception|\Error $e) {
                $this->logger->error("couldn't delete image of {user} at '{path}': {message}", ['user' => $this->user->username, 'path' => $path, 'message' => \get_class($e).': '.$e->getMessage()]);
            }
        }

        $this->entityManager->beginTransaction();

        // delete the original user, so all the content is cascade deleted
        $this->entityManager->remove($this->user);
        $this->entityManager->flush();

        if ($isLocal) {
            // recreate a user with the same name, so this handle is blocked
            $user = $this->userManager->create($userDto, verifyUserEmail: false, rateLimit: false);
            $user->isDeleted = true;
            $user->markedForDeletionAt = null;
            $user->isVerified = false;
            $user->privateKey = $privateKey;
            $user->publicKey = $publicKey;
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->sendDeleteMessages($inboxes, $user);
        }
        $this->entityManager->commit();
    }

    private function getInboxes(): array
    {
        return array_unique(array_filter($this->userManager->getAllInboxesOfInteractions($this->user)));
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

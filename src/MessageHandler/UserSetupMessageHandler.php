<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Contracts\MessageInterface;
use App\Message\UserSetupMessage;
use App\Repository\UserRepository;
use App\Service\InstanceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class UserSetupMessageHandler extends MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
        private readonly InstanceManager $instanceManager,
        private readonly UserRepository $repository,
    ) {
        parent::__construct($this->entityManager, $this->kernel);
    }

    public function __invoke(UserSetupMessage $message): void
    {
        $this->workWrapper($message);
    }

    public function doWork(MessageInterface $message): void
    {
        if (!($message instanceof UserSetupMessage)) {
            throw new \LogicException();
        }

        $user = $this->repository->find($message->userId);
        if (!$user) {
            throw new UnrecoverableMessageHandlingException('User not found');
        }

        $this->instanceManager->applyGlobalInstanceBlocksToUser($user);
    }
}

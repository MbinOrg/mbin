<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Contracts\MessageInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws Exception
     */
    public function workWrapper(MessageInterface $message): void
    {
        // when we are in the test environment this would throw: ConnectionException: There is no active transaction.
        if ('test' !== $this->kernel->getEnvironment()) {
            $conn = $this->entityManager->getConnection();
            if (!$conn->isConnected()) {
                $conn->connect();
            }

            $conn->transactional(fn () => $this->doWork($message));

            $conn->close();
        } else {
            $this->doWork($message);
        }
    }

    abstract public function doWork(MessageInterface $message): void;
}

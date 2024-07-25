<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\Contracts\MessageInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

abstract class MbinMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws Exception
     */
    public function workWrapper(MessageInterface $message): void
    {
        $conn = $this->entityManager->getConnection();
        if (!$conn->isConnected()) {
            $conn->connect();
        }

        $conn->transactional(fn () => $this->doWork($message));

        $conn->close();
    }

    abstract public function doWork(MessageInterface $message): void;
}

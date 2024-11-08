<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ClearDeadMessagesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ClearDeadMessagesHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ClearDeadMessagesMessage $message): void
    {
        $this->logger->info('Clearing dead messages');
        $sql = 'DELETE FROM messenger_messages WHERE queue_name = :queue_name';
        $this->entityManager->createNativeQuery($sql, new ResultSetMapping())
            ->setParameter('queue_name', 'dead')
            ->getResult();
    }
}

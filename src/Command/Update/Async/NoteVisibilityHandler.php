<?php

declare(strict_types=1);

namespace App\Command\Update\Async;

use App\Entity\Contracts\VisibilityInterface;
use App\Factory\WwwHttpClientFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
readonly class NoteVisibilityHandler
{
    private HttpClientInterface $client;

    public function __construct(
        private EntityManagerInterface $entityManager,
        WwwHttpClientFactory $clientFactory,
    ) {
        $this->client = $clientFactory->getClient();
    }

    public function __invoke(NoteVisibilityMessage $message): void
    {
        $repo = $this->entityManager->getRepository($message->class);

        $entity = $repo->find($message->id);
        $req = $this->client->request('GET', $entity->apId, [
            'headers' => [
                'Accept' => 'application/activity+json,application/ld+json,application/json',
            ],
        ]);

        if (Response::HTTP_NOT_FOUND === $req->getStatusCode()) {
            $entity->visibility = VisibilityInterface::VISIBILITY_PRIVATE;
            $this->entityManager->flush();
        }
    }
}

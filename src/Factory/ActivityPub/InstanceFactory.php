<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\ContextsProvider;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InstanceFactory
{
    public function __construct(
        private string $kbinDomain,
        private readonly ApHttpClientInterface $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function create(bool $includeContext = true): array
    {
        $actor = $this->urlGenerator->generate('ap_instance', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $result = [
            '@context' => $this->contextProvider->referencedContexts(),
            'id' => $actor,
            'type' => 'Application',
            'name' => 'Mbin',
            'inbox' => $this->urlGenerator->generate('ap_instance_inbox', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'outbox' => $this->urlGenerator->generate('ap_instance_outbox', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'preferredUsername' => $this->kbinDomain,
            'manuallyApprovesFollowers' => true,
            'publicKey' => [
                'id' => $actor.'#main-key',
                'owner' => $actor,
                'publicKeyPem' => $this->client->getInstancePublicKey(),
            ],
            'published' => ($this->userRepository->findOldestUser()?->createdAt ?? new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        if (!$includeContext) {
            unset($result['@context']);
        }

        return $result;
    }

    public function getTargetUrl(): string
    {
        return 'https://'.$this->kbinDomain;
    }
}

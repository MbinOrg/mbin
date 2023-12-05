<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Service\ActivityPub\ApHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InstanceFactory
{
    public function __construct(
        private string $kbinDomain,
        private readonly ApHttpClient $client,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(): array
    {
        $actor = 'https://'.$this->kbinDomain.'/i/actor';

        return [
            '@context' => [
                ActivityPubActivityInterface::CONTEXT_URL,
                ActivityPubActivityInterface::SECURITY_URL,
                [
                    'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
                ],
            ],
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
            'url' => 'https://'.$this->kbinDomain.'/instance-actor',
        ];
    }
}

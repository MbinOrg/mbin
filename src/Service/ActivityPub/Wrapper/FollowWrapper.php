<?php

declare(strict_types=1);

namespace App\Service\ActivityPub\Wrapper;

use App\Entity\Contracts\ActivityPubActivityInterface;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class FollowWrapper
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[ArrayShape([
        '@context' => 'string',
        'id' => 'string',
        'type' => 'string',
        'actor' => 'string',
        'object' => 'string',
    ])]
    public function build(
        string $follower,
        string $following,
    ): array {
        $id = Uuid::v4()->toRfc4122();

        return [
            '@context' => ActivityPubActivityInterface::CONTEXT_URL,
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'type' => 'Follow',
            'actor' => $follower,
            'object' => $following,
        ];
    }
}

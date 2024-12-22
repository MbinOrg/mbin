<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TombstoneFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(string $id): array
    {
        return [
            'id' => $id,
            'type' => 'Tombstone',
        ];
    }

    public function createForUser(User $user): array
    {
        return $this->create($this->urlGenerator->generate('ap_user', ['username' => $user->username], UrlGeneratorInterface::ABSOLUTE_URL));
    }
}

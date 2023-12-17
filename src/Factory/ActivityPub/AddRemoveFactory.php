<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Magazine;
use App\Entity\User;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class AddRemoveFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildAdd(User $actor, User $added, Magazine $magazine): array
    {
        return $this->build($actor, $added, $magazine, 'Add');
    }

    public function buildRemove(User $actor, User $removed, Magazine $magazine): array
    {
        return $this->build($actor, $removed, $magazine, 'Remove');
    }

    #[ArrayShape([
        '@context' => 'array',
        'id' => 'string',
        'actor' => 'string',
        'to' => 'array',
        'object' => 'string',
        'cc' => 'array',
        'type' => 'string',
        'target' => 'string',
        'audience' => 'string',
    ])]
    private function build(User $actor, User $targetUser, Magazine $magazine, string $type): array
    {
        $id = Uuid::v4()->toRfc4122();

        return [
            '@context' => [ActivityPubActivityInterface::CONTEXT_URL, ActivityPubActivityInterface::SECURITY_URL],
            'id' => $this->urlGenerator->generate('ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL),
            'actor' => $actor->apId ?? $this->urlGenerator->generate('ap_user', ['username' => $actor->username], UrlGeneratorInterface::ABSOLUTE_URL),
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'object' => $targetUser->apId ?? $this->urlGenerator->generate('ap_user', ['username' => $targetUser->username], UrlGeneratorInterface::ABSOLUTE_URL),
            'cc' => [$magazine->apId ?? $this->urlGenerator->generate('ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL)],
            'type' => $type,
            'target' => $magazine->apAttributedToUrl ?? $this->urlGenerator->generate('ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
            'audience' => $magazine->apId ?? $this->urlGenerator->generate('ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}

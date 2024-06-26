<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Service\ActivityPub\ContextsProvider;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class AddRemoveFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
    ) {
    }

    public function buildAddModerator(User $actor, User $added, Magazine $magazine): array
    {
        $url = $magazine->apAttributedToUrl ?? $this->urlGenerator->generate(
            'ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );
        $addedUserUrl = null !== $added->apId ? $added->apPublicUrl : $this->urlGenerator->generate(
            'ap_user', ['username' => $added->username], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $addedUserUrl, $magazine, 'Add', $url);
    }

    public function buildRemoveModerator(User $actor, User $removed, Magazine $magazine): array
    {
        $url = $magazine->apAttributedToUrl ?? $this->urlGenerator->generate(
            'ap_magazine_moderators', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );
        $removedUserUrl = null !== $removed->apId ? $removed->apPublicUrl : $this->urlGenerator->generate(
            'ap_user', ['username' => $removed->username], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $removedUserUrl, $magazine, 'Remove', $url);
    }

    public function buildAddPinnedPost(User $actor, Entry $added): array
    {
        $url = null !== $added->magazine->apId ? $added->magazine->apFeaturedUrl : $this->urlGenerator->generate(
            'ap_magazine_pinned', ['name' => $added->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );
        $entryUrl = null !== $added->apId ?? $this->urlGenerator->generate(
            'ap_entry', ['entry_id' => $added->getId(), 'magazine_name' => $added->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $entryUrl, $added->magazine, 'Add', $url);
    }

    public function buildRemovePinnedPost(User $actor, Entry $removed): array
    {
        $url = null !== $removed->magazine->apId ? $removed->magazine->apFeaturedUrl : $this->urlGenerator->generate(
            'ap_magazine_pinned', ['name' => $removed->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );
        $entryUrl = $removed->apId ?? $this->urlGenerator->generate(
            'ap_entry', ['entry_id' => $removed->getId(), 'magazine_name' => $removed->magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->build($actor, $entryUrl, $removed->magazine, 'Remove', $url);
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
    private function build(User $actor, string $targetObjectUrl, Magazine $magazine, string $type, string $collectionUrl): array
    {
        $id = Uuid::v4()->toRfc4122();

        return [
            '@context' => $this->contextProvider->referencedContexts(),
            'id' => $this->urlGenerator->generate(
                'ap_object', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'actor' => $actor->apId ?? $this->urlGenerator->generate(
                'ap_user', ['username' => $actor->username], UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'to' => [ActivityPubActivityInterface::PUBLIC_URL],
            'object' => $targetObjectUrl,
            'cc' => [
                $magazine->apId ?? $this->urlGenerator->generate(
                    'ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
            'type' => $type,
            'target' => $collectionUrl,
            'audience' => $magazine->apId ?? $this->urlGenerator->generate(
                'ap_magazine', ['name' => $magazine->name], UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ];
    }
}

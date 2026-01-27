<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\User;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ImageManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PersonFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly ImageManagerInterface $imageManager,
        private readonly MarkdownConverter $markdownConverter,
    ) {
    }

    public function create(User $user, bool $context = true): array
    {
        if ($context) {
            $person['@context'] = $this->contextProvider->referencedContexts();
        }

        $person = array_merge(
            $person ?? [], [
                'id' => $this->getActivityPubId($user),
                'type' => $user->type,
                'name' => $user->username,
                'preferredUsername' => $user->username,
                'inbox' => $this->urlGenerator->generate(
                    'ap_user_inbox',
                    ['username' => $user->username],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'outbox' => $this->urlGenerator->generate(
                    'ap_user_outbox',
                    ['username' => $user->username],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'url' => $this->getActivityPubId($user),
                'manuallyApprovesFollowers' => false,
                'discoverable' => $user->apDiscoverable,
                'published' => $user->createdAt->format(DATE_ATOM),
                'following' => $this->urlGenerator->generate(
                    'ap_user_following',
                    ['username' => $user->username],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'followers' => $this->getActivityPubFollowersId($user),
                'publicKey' => [
                    'owner' => $this->getActivityPubId($user),
                    'id' => $this->getActivityPubId($user).'#main-key',
                    'publicKeyPem' => $user->publicKey,
                ],
                'endpoints' => [
                    'sharedInbox' => $this->urlGenerator->generate(
                        'ap_shared_inbox',
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                ],
            ]
        );

        if ($user->about) {
            $person['summary'] = $this->markdownConverter->convertToHtml(
                $user->about,
                context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub],
            );
        }

        if ($user->cover) {
            $person['image'] = [
                'type' => 'Image',
                'url' => $this->imageManager->getUrl($user->cover),
                // @todo media url
            ];
        }

        if ($user->avatar) {
            $person['icon'] = [
                'type' => 'Image',
                'url' => $this->imageManager->getUrl($user->avatar),
                // @todo media url
            ];
        }

        return $person;
    }

    public function getActivityPubId(User $user): string
    {
        if ($user->apId) {
            return $user->apProfileId;
        }

        return $this->urlGenerator->generate(
            'ap_user',
            ['username' => $user->username],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function getActivityPubFollowersId(User $user): string
    {
        if ($user->apId) {
            return $user->apFollowersUrl;
        }

        return $this->urlGenerator->generate(
            'ap_user_followers',
            ['username' => $user->username],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}

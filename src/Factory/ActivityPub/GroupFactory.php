<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Magazine;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ImageManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GroupFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MarkdownConverter $markdownConverter,
        private readonly ContextsProvider $contextProvider,
        private readonly ImageManager $imageManager
    ) {
    }

    public function create(Magazine $magazine, bool $includeContext = true): array
    {
        $markdownSummary = $magazine->description ?? '';

        if (!empty($magazine->rules)) {
            $markdownSummary .= (!empty($markdownSummary) ? "\r\n\r\n" : '')."### Rules\r\n\r\n".$magazine->rules;
        }

        $summary = !empty($markdownSummary) ? $this->markdownConverter->convertToHtml(
            $markdownSummary,
            [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub],
        ) : '';

        $group = [
            'type' => 'Group',
            '@context' => $this->contextProvider->referencedContexts(),
            'id' => $this->getActivityPubId($magazine),
            'name' => $magazine->title, // lemmy
            'preferredUsername' => $magazine->name,
            'inbox' => $this->urlGenerator->generate(
                'ap_magazine_inbox',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'outbox' => $this->urlGenerator->generate(
                'ap_magazine_outbox',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'followers' => $this->urlGenerator->generate(
                'ap_magazine_followers',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'featured' => $this->urlGenerator->generate(
                'ap_magazine_pinned',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'url' => $this->getActivityPubId($magazine),
            'publicKey' => [
                'owner' => $this->getActivityPubId($magazine),
                'id' => $this->getActivityPubId($magazine).'#main-key',
                'publicKeyPem' => $magazine->publicKey,
            ],
            'summary' => $summary,
            'source' => [
                'content' => $markdownSummary,
                'mediaType' => 'text/markdown',
            ],
            'sensitive' => $magazine->isAdult,
            'attributedTo' => $this->urlGenerator->generate(
                'ap_magazine_moderators',
                ['name' => $magazine->name],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'postingRestrictedToMods' => $magazine->postingRestrictedToMods,
            'endpoints' => [
                'sharedInbox' => $this->urlGenerator->generate(
                    'ap_shared_inbox',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
            'published' => $magazine->createdAt->format(DATE_ATOM),
            'updated' => $magazine->lastActive ?
                $magazine->lastActive->format(DATE_ATOM)
                : $magazine->createdAt->format(DATE_ATOM),
        ];

        if ($magazine->icon) {
            $group['icon'] = [
                'type' => 'Image',
                'url' => $this->imageManager->getUrl($magazine->icon),
            ];
        }

        if (!$includeContext) {
            unset($group['@context']);
        }

        return $group;
    }

    public function getActivityPubId(Magazine $magazine): string
    {
        if ($magazine->apId) {
            return $magazine->apProfileId;
        }

        return $this->urlGenerator->generate(
            'ap_magazine',
            ['name' => $magazine->name],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}

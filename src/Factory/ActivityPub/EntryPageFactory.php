<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ApHttpClient;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\ImageWrapper;
use App\Service\ActivityPub\Wrapper\MentionsWrapper;
use App\Service\ActivityPub\Wrapper\TagsWrapper;
use App\Service\ActivityPubManager;
use App\Service\ImageManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EntryPageFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly GroupFactory $groupFactory,
        private readonly ImageManager $imageManager,
        private readonly ImageWrapper $imageWrapper,
        private readonly TagsWrapper $tagsWrapper,
        private readonly MentionsWrapper $mentionsWrapper,
        private readonly ApHttpClient $client,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MarkdownConverter $markdownConverter
    ) {
    }

    public function create(Entry $entry, bool $context = false): array
    {
        if ($context) {
            $page['@context'] = $this->contextProvider->referencedContexts();
        }

        $tags = $entry->tags ?? [];
        if ('random' !== $entry->magazine->name && !$entry->magazine->apId) { // @todo
            $tags[] = $entry->magazine->name;
        }

        $cc = [];
        if ($followersUrl = $entry->user->getFollowerUrl($this->client, $this->urlGenerator, null !== $entry->apId)) {
            $cc[] = $followersUrl;
        }

        $page = array_merge($page ?? [], [
            'id' => $this->getActivityPubId($entry),
            'type' => 'Page',
            'attributedTo' => $this->activityPubManager->getActorProfileId($entry->user),
            'inReplyTo' => null,
            'to' => [
                $this->groupFactory->getActivityPubId($entry->magazine),
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc' => $cc,
            'name' => $entry->title,
            'content' => $entry->body ? $this->markdownConverter->convertToHtml(
                $entry->body,
                [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub]
            ) : null,
            'summary' => $entry->getShortTitle().' '.implode(
                ' ',
                array_map(fn ($val) => '#'.$val, $tags)
            ),
            'mediaType' => 'text/html',
            'source' => $entry->body ? [
                'content' => $entry->body,
                'mediaType' => 'text/markdown',
            ] : null,
            'url' => $this->getUrl($entry),
            'tag' => array_merge(
                $this->tagsWrapper->build($tags),
                $this->mentionsWrapper->build($entry->mentions ?? [], $entry->body)
            ),
            'commentsEnabled' => true,
            'sensitive' => $entry->isAdult(),
            'stickied' => $entry->sticky,
            'published' => $entry->createdAt->format(DATE_ATOM),
        ]);

        $page['contentMap'] = [
            $entry->lang => $page['content'],
        ];

        if ($entry->url) {
            $page['source'] = $entry->url;
            $page['attachment'] = [
                [
                    'href' => $this->getUrl($entry),
                    'type' => 'Link',
                ],
            ];
        } else {
            if ($entry->image) {
                $page = $this->imageWrapper->build($page, $entry->image, $entry->title);
            }
        }

        if ($entry->body) {
            $page['to'] = array_unique(
                array_merge($page['to'], $this->activityPubManager->createCcFromBody($entry->body))
            );
        }

        return $page;
    }

    public function getActivityPubId(Entry $entry): string
    {
        if ($entry->apId) {
            return $entry->apId;
        }

        return $this->urlGenerator->generate(
            'ap_entry',
            ['magazine_name' => $entry->magazine->name, 'entry_id' => $entry->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function getUrl(Entry $entry): string
    {
        $imageUrl = $this->imageManager->getUrl($entry->image);

        if (Entry::ENTRY_TYPE_IMAGE === $entry->type && $imageUrl) {
            return $this->imageManager->getUrl($entry->image);
        }

        return $entry->url ?? $this->getActivityPubId($entry);
    }
}

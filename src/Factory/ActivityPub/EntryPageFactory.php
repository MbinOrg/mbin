<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Entry;
use App\Factory\Contract\ActivityFactoryInterface;
use App\Factory\Entry\EntryUrlFactory;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\ImageWrapper;
use App\Service\ActivityPub\Wrapper\MentionsWrapper;
use App\Service\ActivityPub\Wrapper\TagsWrapper;
use App\Service\ActivityPubManager;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActivityPubActivityInterface<Entry>
 */
class EntryPageFactory implements SwitchableService, ActivityFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly GroupFactory $groupFactory,
        private readonly ImageWrapper $imageWrapper,
        private readonly TagsWrapper $tagsWrapper,
        private readonly MentionsWrapper $mentionsWrapper,
        private readonly ApHttpClientInterface $client,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MarkdownConverter $markdownConverter,
        private readonly EntryUrlFactory $entryUrlFactory,
    ) {
    }

    public function getSupportedTypes(): array
    {
        return [Entry::class];
    }

    /**
     * @inheritDoc
     * @param Entry $subject
     */
    public function create($subject, array $tags, bool $context = false): array
    {
        if ($context) {
            $page['@context'] = $this->contextProvider->referencedContexts();
        }

        if ('random' !== $subject->magazine->name && !$subject->magazine->apId) { // @todo
            $tags[] = $subject->magazine->name;
        }

        $cc = [];
        if ($followersUrl = $subject->user->getFollowerUrl($this->client, $this->urlGenerator, null !== $subject->apId)) {
            $cc[] = $followersUrl;
        }

        $page = array_merge($page ?? [], [
            'id' => $this->getActivityPubId($subject),
            'type' => 'Page',
            'attributedTo' => $this->activityPubManager->getActorProfileId($subject->user),
            'inReplyTo' => null,
            'to' => [
                $this->groupFactory->getActivityPubId($subject->magazine),
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc' => $cc,
            'name' => $subject->title,
            'audience' => $this->groupFactory->getActivityPubId($subject->magazine),
            'content' => $subject->body ? $this->markdownConverter->convertToHtml(
                $subject->body,
                context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub]
            ) : null,
            'summary' => $subject->getShortTitle().' '.implode(
                ' ',
                array_map(fn ($val) => '#'.$val, $tags)
            ),
            'mediaType' => 'text/html',
            'source' => $subject->body ? [
                'content' => $subject->body,
                'mediaType' => 'text/markdown',
            ] : null,
            'tag' => array_merge(
                $this->tagsWrapper->build($tags),
                $this->mentionsWrapper->build($subject->mentions ?? [], $subject->body)
            ),
            'commentsEnabled' => !$subject->isLocked,
            'sensitive' => $subject->isAdult(),
            'stickied' => $subject->sticky,
            'published' => $subject->createdAt->format(DATE_ATOM),
        ]);

        $page['contentMap'] = [
            $subject->lang => $page['content'],
        ];

        if ($subject->url) {
            $page['source'] = $subject->url;
            $page['attachment'][] = [
                'href' => $subject->url,
                'type' => 'Link',
            ];
        }

        if ($subject->image) {
            // We do not know whether the image comes from an embed.
            // Even if $entry->hasEmbed is true that does not mean that the image is from the embed
            $page = $this->imageWrapper->build($page, $subject->image, $subject->title);
        }

        if ($subject->body) {
            $page['to'] = array_unique(
                array_merge($page['to'], $this->activityPubManager->createCcFromBody($subject->body))
            );
        }

        return $page;
    }

    public function getActivityPubId(Entry $entry): string
    {
        return $this->entryUrlFactory->getActivityPubId($entry);
    }
}

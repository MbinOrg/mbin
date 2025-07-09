<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Post;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\ImageWrapper;
use App\Service\ActivityPub\Wrapper\MentionsWrapper;
use App\Service\ActivityPub\Wrapper\TagsWrapper;
use App\Service\ActivityPubManager;
use App\Service\MentionManager;
use App\Service\TagExtractor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PostNoteFactory
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
        private readonly MentionManager $mentionManager,
        private readonly TagExtractor $tagExtractor,
        private readonly MarkdownConverter $markdownConverter,
    ) {
    }

    public function create(Post $post, array $tags, bool $context = false): array
    {
        if ($context) {
            $note['@context'] = $this->contextProvider->referencedContexts();
        }

        if ('random' !== $post->magazine->name && !$post->magazine->apId) { // @todo
            $tags[] = $post->magazine->name;
        }

        $body = $this->tagExtractor->joinTagsToBody(
            $post->body,
            $tags
        );

        $cc = [];
        if ($followersUrl = $post->user->getFollowerUrl($this->client, $this->urlGenerator, null !== $post->apId)) {
            $cc[] = $followersUrl;
        }

        $note = array_merge($note ?? [], [
            'id' => $this->getActivityPubId($post),
            'type' => 'Note',
            'attributedTo' => $this->activityPubManager->getActorProfileId($post->user),
            'inReplyTo' => null,
            'to' => [
                $this->groupFactory->getActivityPubId($post->magazine),
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc' => $cc,
            'audience' => $this->groupFactory->getActivityPubId($post->magazine),
            'sensitive' => $post->isAdult(),
            'stickied' => $post->sticky,
            'content' => $this->markdownConverter->convertToHtml(
                $body,
                context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub],
            ),
            'mediaType' => 'text/html',
            'source' => $post->body ? [
                'content' => $body,
                'mediaType' => 'text/markdown',
            ] : null,
            'url' => $this->getActivityPubId($post),
            'tag' => array_merge(
                $this->tagsWrapper->build($tags),
                $this->mentionsWrapper->build($post->mentions ?? [], $post->body)
            ),
            'commentsEnabled' => true,
            'published' => $post->createdAt->format(DATE_ATOM),
        ]);

        $note['contentMap'] = [
            $post->lang => $note['content'],
        ];

        if ($post->image) {
            $note = $this->imageWrapper->build($note, $post->image, $post->getShortTitle());
        }

        $note['to'] = array_unique(array_merge($note['to'], $this->activityPubManager->createCcFromBody($post->body)));

        return $note;
    }

    public function getActivityPubId(Post $post): string
    {
        if ($post->apId) {
            return $post->apId;
        }

        return $this->urlGenerator->generate(
            'ap_post',
            ['magazine_name' => $post->magazine->name, 'post_id' => $post->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}

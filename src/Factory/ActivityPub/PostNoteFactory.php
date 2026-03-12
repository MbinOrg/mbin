<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\Contract\ActivityFactoryInterface;
use App\Factory\Post\PostUrlFactory;
use App\Markdown\MarkdownConverter;
use App\Markdown\RenderTarget;
use App\Service\ActivityPub\ApHttpClientInterface;
use App\Service\ActivityPub\ContextsProvider;
use App\Service\ActivityPub\Wrapper\ImageWrapper;
use App\Service\ActivityPub\Wrapper\MentionsWrapper;
use App\Service\ActivityPub\Wrapper\TagsWrapper;
use App\Service\ActivityPubManager;
use App\Service\Contracts\SwitchableService;
use App\Service\MentionManager;
use App\Service\TagExtractor;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActivityPubActivityInterface<Post>
 */
class PostNoteFactory implements SwitchableService, ActivityFactoryInterface
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
        private readonly TagExtractor $tagExtractor,
        private readonly MarkdownConverter $markdownConverter,
        private readonly PostUrlFactory $urlFactory,
    ) {
    }

    public function getSupportedTypes(): array
    {
        return [Post::class];
    }

    /**
     * @inheritDoc
     * @param Post $subject
    */
    public function create($subject, array $tags, bool $context = false): array
    {
        if ($context) {
            $note['@context'] = $this->contextProvider->referencedContexts();
        }

        if ('random' !== $subject->magazine->name && !$subject->magazine->apId) { // @todo
            $tags[] = $subject->magazine->name;
        }

        $body = $this->tagExtractor->joinTagsToBody(
            $subject->body,
            $tags
        );

        $cc = [];
        if ($followersUrl = $subject->user->getFollowerUrl($this->client, $this->urlGenerator, null !== $subject->apId)) {
            $cc[] = $followersUrl;
        }

        $note = array_merge($note ?? [], [
            'id' => $this->getActivityPubId($subject),
            'type' => 'Note',
            'attributedTo' => $this->activityPubManager->getActorProfileId($subject->user),
            'inReplyTo' => null,
            'to' => [
                $this->groupFactory->getActivityPubId($subject->magazine),
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc' => $cc,
            'audience' => $this->groupFactory->getActivityPubId($subject->magazine),
            'sensitive' => $subject->isAdult(),
            'stickied' => $subject->sticky,
            'content' => $this->markdownConverter->convertToHtml(
                $body,
                context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub],
            ),
            'mediaType' => 'text/html',
            'source' => $subject->body ? [
                'content' => $body,
                'mediaType' => 'text/markdown',
            ] : null,
            'url' => $this->getActivityPubId($subject),
            'tag' => array_merge(
                $this->tagsWrapper->build($tags),
                $this->mentionsWrapper->build($subject->mentions ?? [], $subject->body)
            ),
            'commentsEnabled' => !$subject->isLocked,
            'published' => $subject->createdAt->format(DATE_ATOM),
        ]);

        $note['contentMap'] = [
            $subject->lang => $note['content'],
        ];

        if ($subject->image) {
            $note = $this->imageWrapper->build($note, $subject->image, $subject->getShortTitle());
        }

        $note['to'] = array_unique(array_merge($note['to'], $this->activityPubManager->createCcFromBody($subject->body)));

        return $note;
    }

    public function getActivityPubId(Post $post): string
    {
        return $this->urlFactory->getActivityPubId($post);
    }
}

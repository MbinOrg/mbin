<?php

declare(strict_types=1);

namespace App\Factory\ActivityPub;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\PostComment;
use App\Factory\Contract\ActivityFactoryInterface;
use App\Factory\Post\PostCommentUrlFactory;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ActivityPubActivityInterface<PostComment>
 */
class PostCommentNoteFactory implements SwitchableService, ActivityFactoryInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ContextsProvider $contextProvider,
        private readonly PostNoteFactory $postNoteFactory,
        private readonly ImageWrapper $imageWrapper,
        private readonly GroupFactory $groupFactory,
        private readonly TagsWrapper $tagsWrapper,
        private readonly MentionsWrapper $mentionsWrapper,
        private readonly ApHttpClientInterface $client,
        private readonly ActivityPubManager $activityPubManager,
        private readonly MarkdownConverter $markdownConverter,
        private readonly PostCommentUrlFactory $postCommentUrlFactory,
    ) {
    }

    public function getSupportedTypes(): array
    {
        return [PostComment::class];
    }

    /**
     * @inheritDoc
     * @param PostComment $subject
     */
    public function create($subject, array $tags, bool $context = false): array
    {
        if ($context) {
            $note['@context'] = $this->contextProvider->referencedContexts();
        }

        if ('random' !== $subject->magazine->name && !$subject->magazine->apId) { // @todo
            $tags[] = $subject->magazine->name;
        }

        $cc = [$this->groupFactory->getActivityPubId($subject->magazine)];
        if ($followersUrl = $subject->user->getFollowerUrl($this->client, $this->urlGenerator, null !== $subject->apId)) {
            $cc[] = $followersUrl;
        }

        $note = array_merge($note ?? [], [
            'id' => $this->getActivityPubId($subject),
            'type' => 'Note',
            'attributedTo' => $this->activityPubManager->getActorProfileId($subject->user),
            'inReplyTo' => $this->getReplyTo($subject),
            'to' => [
                ActivityPubActivityInterface::PUBLIC_URL,
            ],
            'cc' => $cc,
            'audience' => $this->groupFactory->getActivityPubId($subject->magazine),
            'sensitive' => $subject->post->isAdult(),
            'content' => $this->markdownConverter->convertToHtml(
                $subject->body,
                context: [MarkdownConverter::RENDER_TARGET => RenderTarget::ActivityPub],
            ),
            'mediaType' => 'text/html',
            'source' => $subject->body ? [
                'content' => $subject->body,
                'mediaType' => 'text/markdown',
            ] : null,
            'url' => $this->getActivityPubId($subject),
            'tag' => array_merge(
                $this->tagsWrapper->build($tags),
                $this->mentionsWrapper->build($subject->mentions ?? [], $subject->body)
            ),
            'published' => $subject->createdAt->format(DATE_ATOM),
        ]);

        $note['contentMap'] = [
            $subject->lang => $note['content'],
        ];

        if ($subject->image) {
            $note = $this->imageWrapper->build($note, $subject->image, $subject->getShortTitle());
        }

        $mentions = [];
        foreach ($subject->mentions ?? [] as $mention) {
            try {
                $profileId = $this->activityPubManager->findActorOrCreate($mention)?->apProfileId;
                if ($profileId) {
                    $mentions[] = $profileId;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $note['to'] = array_values(
            array_unique(
                array_merge(
                    $note['to'],
                    $mentions,
                    $this->activityPubManager->createCcFromBody($subject->body),
                    [$this->getReplyToAuthor($subject)],
                )
            )
        );

        return $note;
    }

    public function getActivityPubId(PostComment $comment): string
    {
        return $this->postCommentUrlFactory->getActivityPubId($comment);
    }

    private function getReplyTo(PostComment $comment): string
    {
        return $comment->parent ? $this->getActivityPubId($comment->parent) : $this->postNoteFactory->getActivityPubId($comment->post);
    }

    private function getReplyToAuthor(PostComment $comment): string
    {
        return $comment->parent
            ? $this->activityPubManager->getActorProfileId($comment->parent->user)
            : $this->activityPubManager->getActorProfileId($comment->post->user);
    }
}

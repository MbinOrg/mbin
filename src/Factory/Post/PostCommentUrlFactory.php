<?php

namespace App\Factory\Post;

use App\Entity\PostComment;
use App\Factory\Contract\ContentUrlFactory;
use App\Service\Contracts\SwitchableService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements SwitchableService
 * @implements ContentUrlFactory<PostComment>
 */
readonly class PostCommentUrlFactory implements SwitchableService, ContentUrlFactory
{

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private PostUrlFactory $postUrlFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [PostComment::class];
    }

    function getActivityPubId($subject): string
    {
        if ($subject->apId) {
            return $subject->apId;
        }

        return $this->urlGenerator->generate('ap_post_comment', [
            'magazine_name' => $subject->magazine->name,
            'post_id' => $subject->post->getId(),
            'comment_id' => $subject->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    function getLocalUrl($subject): string
    {
        return $this->postUrlFactory->getLocalUrl($subject).'#post-comment-'.$subject->getId();
    }
}

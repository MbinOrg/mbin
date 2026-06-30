<?php

namespace App\Factory\Post;

use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\DTO\PostCommentResponseDto;
use App\DTO\PostResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\Contract\ContentDtoFactory;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Service\Contracts\SwitchableService;

/**
 * @implements SwitchableService
 * @implements ContentDtoFactory<Post, PostResponseDto>
 */
readonly class PostDtoFactory implements SwitchableService, ContentDtoFactory
{

    public function __construct(
        private PostFactory $postFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [Post::class];
    }

    public function createResponseDto($subject, array $hashtags): PostResponseDto
    {
        return $this->postFactory->createResponseDto($subject, $hashtags);
    }
}

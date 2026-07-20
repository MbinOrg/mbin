<?php

namespace App\Factory\Post;

use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\DTO\PostCommentResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\PostComment;
use App\Factory\Contract\ContentDtoFactory;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\PostCommentFactory;
use App\Service\Contracts\SwitchableService;

/**
 * @implements SwitchableService
 * @implements ContentDtoFactory<PostComment, PostCommentResponseDto>
 */
readonly class PostCommentDtoFactory implements SwitchableService, ContentDtoFactory
{

    public function __construct(
        private PostCommentFactory $commentFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [PostComment::class];
    }

    public function createResponseDto($subject, array $hashtags): PostCommentResponseDto
    {
        return $this->commentFactory->createResponseDto($subject, $hashtags);
    }
}

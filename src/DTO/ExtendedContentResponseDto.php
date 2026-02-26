<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

/**
 * This class is just used to have a single return type in case of an array that can contain multiple content types.
 */
#[OA\Schema()]
class ExtendedContentResponseDto
{
    public function __construct(
        public ?EntryResponseDto $entry = null,
        public ?PostResponseDto $post = null,
        public ?EntryCommentResponseDto $entryComment = null,
        public ?PostCommentResponseDto $postComment = null,
    ) {
    }
}

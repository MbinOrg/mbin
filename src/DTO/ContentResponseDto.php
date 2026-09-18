<?php

declare(strict_types=1);

namespace App\DTO;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * This class is just used to have a single return type in case of an array that can contain multiple content types.
 */
#[OA\Schema()]
class ContentResponseDto
{
    public function __construct(
        public ?EntryResponseDto $entry = null,
        public ?PostResponseDto $post = null,
        public ?EntryCommentResponseDto $entryComment = null,
        public ?PostCommentResponseDto $postComment = null,
        /** @var ContentBoostResponseDto[]|null */
        #[OA\Property(type: 'array', nullable: true, items: new OA\Items(ref: new Model(type: ContentBoostResponseDto::class)))]
        public ?array $boostedBy = null,
    ) {
    }
}

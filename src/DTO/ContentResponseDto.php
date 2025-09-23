<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * This class is just used to have a single return type in case of an array that can contain multiple content types.
 */
class ContentResponseDto
{
    public function __construct(
        public ?EntryResponseDto $entry = null,
        public ?PostResponseDto $post = null,
    ) {
    }
}

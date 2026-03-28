<?php

namespace App\Factory\Entry;

use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Factory\Contract\ContentDtoFactory;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Service\Contracts\SwitchableService;

/**
 * @implements SwitchableService
 * @implements ContentDtoFactory<EntryComment, EntryCommentResponseDto>
 */
readonly class EntryCommentDtoFactory implements SwitchableService, ContentDtoFactory
{

    public function __construct(
        private EntryCommentFactory $commentFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [EntryComment::class];
    }

    public function createResponseDto($subject, array $hashtags): EntryCommentResponseDto
    {
        return $this->commentFactory->createResponseDto($subject, $hashtags);
    }
}

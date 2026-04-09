<?php

namespace App\Factory\Entry;

use App\DTO\EntryResponseDto;
use App\Entity\Entry;
use App\Factory\Contract\ContentDtoFactory;
use App\Factory\EntryFactory;
use App\Service\Contracts\SwitchableService;

/**
 * @implements SwitchableService
 * @implements ContentDtoFactory<Entry, EntryResponseDto>
 */
readonly class EntryDtoFactory implements SwitchableService, ContentDtoFactory
{

    public function __construct(
        private EntryFactory $entryFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [Entry::class];
    }

    public function createResponseDto($subject, array $hashtags): EntryResponseDto
    {
        return $this->entryFactory->createResponseDto($subject, $hashtags);
    }
}

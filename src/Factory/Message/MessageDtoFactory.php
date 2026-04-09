<?php

namespace App\Factory\Message;

use App\DTO\EntryCommentResponseDto;
use App\DTO\EntryResponseDto;
use App\DTO\MessageResponseDto;
use App\DTO\PostCommentResponseDto;
use App\DTO\PostResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Message;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Factory\Contract\ContentDtoFactory;
use App\Factory\EntryCommentFactory;
use App\Factory\EntryFactory;
use App\Factory\MessageFactory;
use App\Factory\PostCommentFactory;
use App\Factory\PostFactory;
use App\Service\Contracts\SwitchableService;

/**
 * @implements SwitchableService
 * @implements ContentDtoFactory<Message, MessageResponseDto>
 */
readonly class MessageDtoFactory implements SwitchableService, ContentDtoFactory
{

    public function __construct(
        private MessageFactory $messageFactory,
    ){}

    public function getSupportedTypes(): array
    {
        return [Message::class];
    }

    public function createResponseDto($subject, array $hashtags): MessageResponseDto
    {
        return $this->messageFactory->createResponseDto($subject);
    }
}

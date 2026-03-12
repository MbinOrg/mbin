<?php

namespace App\Factory\Contract;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Contracts\HashtagableInterface;

/**
 * @template T of ContentInterface & HashtagableInterface
 * @template RespDto
 */
interface ContentDtoFactory
{

    /**
     * @param T $subject
     * @param string[] $hashtags
     * @return RespDto
     */
    public function createResponseDto($subject, array $hashtags);
}

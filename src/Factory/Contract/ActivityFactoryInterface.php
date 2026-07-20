<?php

namespace App\Factory\Contract;

use App\Entity\Contracts\ContentInterface;

/**
 * @template T of ContentInterface
 */
interface ActivityFactoryInterface
{
    /**
     * @param T $subject
     * @param string[] $tags hashtags
     * @param bool $context whether to include the context or not
     * @return array
     */
    public function create($subject, array $tags, bool $context = false): array;
}

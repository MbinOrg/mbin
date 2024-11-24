<?php

declare(strict_types=1);

namespace App\Message;

use App\Message\Contracts\AsyncMessageInterface;

class UserApplicationAnswerMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $approved,
    ) {
    }
}

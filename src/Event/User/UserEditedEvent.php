<?php

declare(strict_types=1);

namespace App\Event\User;

class UserEditedEvent
{
    public function __construct(
        public int $userId,
    ) {
    }
}

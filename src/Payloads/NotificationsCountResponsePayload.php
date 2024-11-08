<?php

declare(strict_types=1);

namespace App\Payloads;

class NotificationsCountResponsePayload
{
    public function __construct(
        public int $notifications,
        public int $messages,
    ) {
    }
}

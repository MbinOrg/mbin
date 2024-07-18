<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Enums\EPushNotificationType;

class PushNotification
{
    public function __construct(
        public string $message,
        public string $title,
        public ?string $actionUrl = null,
        public ?string $avatarUrl = null,
        public string $iconUrl = '/assets/icons/icon-192-maskable.png',
        public EPushNotificationType $category = EPushNotificationType::Notification,
    ) {
    }
}

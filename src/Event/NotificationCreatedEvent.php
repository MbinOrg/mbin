<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Notification;
use Symfony\Contracts\EventDispatcher\Event;

class NotificationCreatedEvent extends Event
{
    public function __construct(
        public Notification $notification
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum EPushNotificationType: string
{
    case Notification = 'notification';
    case Message = 'message';
}

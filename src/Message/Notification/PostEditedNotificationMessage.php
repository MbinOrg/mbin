<?php

declare(strict_types=1);

namespace App\Message\Notification;

use App\Message\Contracts\AsyncMessageInterface;

class PostEditedNotificationMessage implements AsyncMessageInterface
{
    public function __construct(public int $postId)
    {
    }
}

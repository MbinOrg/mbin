<?php

declare(strict_types=1);

namespace App\Event\Entry;

use App\Entity\Post;
use App\Entity\User;

class PostLockEvent
{
    public function __construct(public Post $post, public ?User $actor)
    {
    }
}

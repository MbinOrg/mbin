<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Hashtag;
use App\Entity\User;

class HashtagBlockChangedEvent
{
    public function __construct(public Hashtag $hashtag, public User $user, public bool $blocked)
    {
    }
}

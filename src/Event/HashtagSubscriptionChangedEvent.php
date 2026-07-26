<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Hashtag;
use App\Entity\User;

class HashtagSubscriptionChangedEvent
{
    public function __construct(public Hashtag $tag, public User $user, bool $subscribed)
    {
    }
}

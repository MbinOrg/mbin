<?php

declare(strict_types=1);

namespace App\Event\Entry;

use App\Entity\Entry;
use App\Entity\User;

class EntryBeforePurgeEvent
{
    public function __construct(public Entry $entry, public User $user)
    {
    }
}

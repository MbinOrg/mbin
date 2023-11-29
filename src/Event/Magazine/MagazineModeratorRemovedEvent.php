<?php

declare(strict_types=1);

namespace App\Event\Magazine;

use App\Entity\Magazine;
use App\Entity\User;

class MagazineModeratorRemovedEvent
{
    public function __construct(public Magazine $magazine, public User $user, public ?User $removedBy)
    {
    }
}

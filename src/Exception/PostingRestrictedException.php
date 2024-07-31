<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Magazine;
use App\Entity\User;

final class PostingRestrictedException extends \Exception
{
    public function __construct(public Magazine $magazine, public User|Magazine $actor)
    {
        if ($this->actor instanceof User) {
            $username = $this->actor->getUsername();
        } else {
            $username = $this->actor->name;
        }
        $m = \sprintf('Posting in magazine %s is restricted to mods and %s is not a mod', $this->magazine->apId ?? $this->magazine->name, $username);
        parent::__construct($m, 0, null);
    }
}

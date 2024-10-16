<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Magazine;
use App\Entity\User;

class SearchDto
{
    public string $q;
    public ?string $type = null;
    public ?User $user = null;
    public ?Magazine $magazine = null;
}

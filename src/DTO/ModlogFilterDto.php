<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Magazine;

class ModlogFilterDto
{
    /**
     * @var string[]
     */
    public array $types;

    public ?Magazine $magazine;
}

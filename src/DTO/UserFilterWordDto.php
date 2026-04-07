<?php

declare(strict_types=1);

namespace App\DTO;

class UserFilterWordDto
{
    public ?string $word = null;

    public bool $exactMatch = false;
}

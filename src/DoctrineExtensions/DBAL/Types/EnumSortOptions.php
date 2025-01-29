<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use App\Enums\ESortOptions;

class EnumSortOptions extends EnumType
{
    public function getName(): string
    {
        return 'EnumSortOptions';
    }

    public function getValues(): array
    {
        return ESortOptions::getValues();
    }
}

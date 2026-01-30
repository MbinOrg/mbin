<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use App\Enums\EDirectMessageSettings;

class EnumDirectMessageSettings extends EnumType
{
    public function getName(): string
    {
        return 'EnumDirectMessageSettings';
    }

    public function getValues(): array
    {
        return EDirectMessageSettings::getValues();
    }
}

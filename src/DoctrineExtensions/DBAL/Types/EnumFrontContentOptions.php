<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use App\Enums\EFrontContentOptions;

class EnumFrontContentOptions extends EnumType
{
    public function getName(): string
    {
        return 'EnumFrontContentOptions';
    }

    public function getValues(): array
    {
        return EFrontContentOptions::getValues();
    }
}

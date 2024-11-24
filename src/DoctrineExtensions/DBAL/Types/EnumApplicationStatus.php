<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use App\Enums\EApplicationStatus;

class EnumApplicationStatus extends EnumType
{
    public function getName(): string
    {
        return 'EnumApplicationStatus';
    }

    public function getValues(): array
    {
        return EApplicationStatus::getValues();
    }
}

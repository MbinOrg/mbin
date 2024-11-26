<?php

declare(strict_types=1);

namespace App\DoctrineExtensions\DBAL\Types;

use App\Enums\ENotificationStatus;

class EnumNotificationStatus extends EnumType
{
    public function getName(): string
    {
        return 'EnumNotificationStatus';
    }

    public function getValues(): array
    {
        return ENotificationStatus::getValues();
    }
}

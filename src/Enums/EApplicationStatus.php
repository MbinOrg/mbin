<?php

declare(strict_types=1);

namespace App\Enums;

use HeyMoon\DoctrinePostgresEnum\Attribute\EnumType;

#[EnumType('enum_application_status')]
enum EApplicationStatus: string
{
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Pending = 'Pending';

    public static function getFromString(string $value): ?EApplicationStatus
    {
        return match ($value) {
            self::Approved->value => self::Approved,
            self::Rejected->value => self::Rejected,
            self::Pending->value => self::Pending,
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return [
            EApplicationStatus::Approved->value,
            EApplicationStatus::Rejected->value,
            EApplicationStatus::Pending->value,
        ];
    }
}

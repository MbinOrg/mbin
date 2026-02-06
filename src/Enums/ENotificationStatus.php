<?php

declare(strict_types=1);

namespace App\Enums;

use HeyMoon\DoctrinePostgresEnum\Attribute\EnumType;

#[EnumType('enum_notification_status')]
enum ENotificationStatus: string
{
    case Default = 'Default';
    case Muted = 'Muted';
    case Loud = 'Loud';

    public static function getFromString(string $value): ?ENotificationStatus
    {
        return match ($value) {
            self::Default->value => self::Default,
            self::Muted->value => self::Muted,
            self::Loud->value => self::Loud,
            default => null,
        };
    }

    public const Values = [
        ENotificationStatus::Default->value,
        ENotificationStatus::Muted->value,
        ENotificationStatus::Loud->value,
    ];

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return self::Values;
    }
}

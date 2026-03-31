<?php

declare(strict_types=1);

namespace App\Enums;

use HeyMoon\DoctrinePostgresEnum\Attribute\EnumType;

#[EnumType('user_type')]
enum EUserType: string
{
    case Person = 'Person';
    case Service = 'Service';
    case Organization = 'Organization';
    case Application = 'Application';

    public static function getFromString(string $value): ?EUserType
    {
        return match ($value) {
            self::Person->value => self::Person,
            self::Service->value => self::Service,
            self::Organization->value => self::Organization,
            self::Application->value => self::Application,
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return [
            self::Person->value,
            self::Service->value,
            self::Organization->value,
            self::Application->value,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum EDirectMessageSettings: string
{
    case Everyone = 'everyone';
    case FollowersOnly = 'followers_only';
    case Nobody = 'nobody';

    public const array OPTIONS = [
        EDirectMessageSettings::Everyone->value,
        EDirectMessageSettings::FollowersOnly->value,
        EDirectMessageSettings::Nobody->value,
    ];

    public static function getFromString(string $value): ?EDirectMessageSettings
    {
        return match ($value) {
            self::Everyone->value => self::Everyone,
            self::FollowersOnly->value => self::FollowersOnly,
            self::Nobody->value => self::Nobody,
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return self::OPTIONS;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum ESortOptions: string
{
    case Hot = 'hot';
    case Top = 'top';
    case Newest = 'newest';
    case Active = 'active';
    case Oldest = 'oldest';
    case Commented = 'commented';

    public static function getFromString(string $value): ?ESortOptions
    {
        return match ($value) {
            self::Hot->value => self::Hot,
            self::Top->value => self::Top,
            self::Newest->value => self::Newest,
            self::Active->value => self::Active,
            self::Oldest->value => self::Oldest,
            self::Commented->value => self::Commented,
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return [
            ESortOptions::Hot->value,
            ESortOptions::Top->value,
            ESortOptions::Newest->value,
            ESortOptions::Active->value,
            ESortOptions::Oldest->value,
            ESortOptions::Commented->value,
        ];
    }
}

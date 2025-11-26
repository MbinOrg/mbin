<?php

declare(strict_types=1);

namespace App\Enums;

use HeyMoon\DoctrinePostgresEnum\Attribute\EnumType;

#[EnumType('enum_front_content_options')]
enum EFrontContentOptions: string
{
    case All = 'all';
    case Threads = 'threads';
    case Microblog = 'microblog';

    public const array OPTIONS = [
        EFrontContentOptions::All->value,
        EFrontContentOptions::Threads->value,
        EFrontContentOptions::Microblog->value,
    ];

    public static function getFromString(string $value): ?EFrontContentOptions
    {
        return match ($value) {
            EFrontContentOptions::All->value => EFrontContentOptions::All,
            EFrontContentOptions::Threads->value => EFrontContentOptions::Threads,
            EFrontContentOptions::Microblog->value => EFrontContentOptions::Microblog,
            default => null,
        };
    }

    /**
     * @return string[]
     */
    public static function getValues(): array
    {
        return [
            ...self::OPTIONS,
            null,
        ];
    }
}

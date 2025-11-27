<?php

declare(strict_types=1);

namespace App\Enums;

enum EFrontContentOptions: string
{
    case Combined = 'combined';
    case Threads = 'threads';
    case Microblog = 'microblog';

    public const array OPTIONS = [
        EFrontContentOptions::Combined->value,
        EFrontContentOptions::Threads->value,
        EFrontContentOptions::Microblog->value,
    ];

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

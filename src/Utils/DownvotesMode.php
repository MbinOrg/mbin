<?php

declare(strict_types=1);

namespace App\Utils;

enum DownvotesMode: string
{
    case Disabled = 'disabled';
    case Hidden = 'hidden';
    case Enabled = 'enabled';

    public static function GetChoices(): array
    {
        return [
            self::Enabled->name => self::Enabled->value,
            self::Hidden->name => self::Hidden->value,
            self::Disabled->name => self::Disabled->value,
        ];
    }
}

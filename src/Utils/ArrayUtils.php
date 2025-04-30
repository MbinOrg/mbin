<?php

declare(strict_types=1);

namespace App\Utils;

class ArrayUtils
{
    public static function numCompareAscending(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    public static function numCompareDescending(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return ($a < $b) ? 1 : -1;
    }
}

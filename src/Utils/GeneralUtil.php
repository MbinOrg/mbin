<?php

declare(strict_types=1);

namespace App\Utils;

class GeneralUtil
{
    public static function shouldPathBeIgnored(array $ignoredPaths, string $path): bool
    {
        $isIgnored = false;
        foreach ($ignoredPaths as $ignoredPath) {
            if (str_starts_with($path, $ignoredPath) || str_starts_with('/'.$path, $ignoredPath)) {
                $isIgnored = true;
                break;
            }
        }

        return $isIgnored;
    }
}

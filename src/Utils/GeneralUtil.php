<?php

declare(strict_types=1);

namespace App\Utils;

use Symfony\Component\Console\Helper\ProgressBar;

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

    public static function useProgressbarFormatsWithMessage(): void
    {
        ProgressBar::setFormatDefinition(ProgressBar::FORMAT_NORMAL, ProgressBar::getFormatDefinition(ProgressBar::FORMAT_NORMAL).' - %message%');
        ProgressBar::setFormatDefinition(ProgressBar::FORMAT_VERBOSE, ProgressBar::getFormatDefinition(ProgressBar::FORMAT_VERBOSE).' - %message%');
        ProgressBar::setFormatDefinition(ProgressBar::FORMAT_VERY_VERBOSE, ProgressBar::getFormatDefinition(ProgressBar::FORMAT_VERY_VERBOSE).' - %message%');
        ProgressBar::setFormatDefinition(ProgressBar::FORMAT_DEBUG, ProgressBar::getFormatDefinition(ProgressBar::FORMAT_DEBUG).' - %message%');
    }
}

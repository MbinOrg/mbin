<?php

declare(strict_types=1);

namespace App\Utils;

enum ExifCleanMode: string
{
    case None = 'none';
    case Sanitize = 'sanitize';
    case Scrub = 'scrub';
}

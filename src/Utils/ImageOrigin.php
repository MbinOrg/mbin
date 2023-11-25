<?php

declare(strict_types=1);

namespace App\Utils;

enum ImageOrigin: string
{
    case Uploaded = 'uploaded';
    case External = 'external';
}

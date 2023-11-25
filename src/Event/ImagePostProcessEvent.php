<?php

declare(strict_types=1);

namespace App\Event;

use App\Utils\ImageOrigin;
use Symfony\Contracts\EventDispatcher\Event;

class ImagePostProcessEvent extends Event
{
    public function __construct(
        public string $source,
        public ImageOrigin $origin,
    ) {
    }
}

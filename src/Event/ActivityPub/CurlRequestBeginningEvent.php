<?php

declare(strict_types=1);

namespace App\Event\ActivityPub;

class CurlRequestBeginningEvent
{
    public function __construct(
        public string $targetUrl,
        public string $method = 'GET',
        public ?string $body = null,
    ) {
    }
}

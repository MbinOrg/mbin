<?php

declare(strict_types=1);

namespace App\Event\ActivityPub;

class CurlRequestFinishedEvent
{
    public function __construct(
        public string $url,
        public bool $wasSuccessful,
        public ?string $responseContent = null,
        public ?\Throwable $exception = null,
    ) {
    }
}

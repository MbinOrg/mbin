<?php

declare(strict_types=1);

namespace App\Event\ActivityPub;

use App\ActivityPub\JsonRd;
use Symfony\Component\HttpFoundation\Request;

class WebfingerResponseEvent
{
    public function __construct(
        public JsonRd $jsonRd,
        public Request $request,
    ) {
    }
}

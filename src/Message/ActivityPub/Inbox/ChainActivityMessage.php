<?php

declare(strict_types=1);

namespace App\Message\ActivityPub\Inbox;

use App\Message\Contracts\ActivityPubResolveInterface;

class ChainActivityMessage implements ActivityPubResolveInterface
{
    public function __construct(
        public array $chain,
        public ?array $parent = null,
        public ?array $announce = null,
        public ?array $like = null,
        public ?array $dislike = null,
    ) {
    }
}

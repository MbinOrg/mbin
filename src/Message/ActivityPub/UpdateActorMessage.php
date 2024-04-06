<?php

declare(strict_types=1);

namespace App\Message\ActivityPub;

use App\Message\Contracts\ActivityPubResolveInterface;

class UpdateActorMessage implements ActivityPubResolveInterface
{
    public function __construct(public string $actorUrl)
    {
    }
}

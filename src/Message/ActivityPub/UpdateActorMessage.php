<?php

declare(strict_types=1);

namespace App\Message\ActivityPub;

use App\Message\Contracts\ActivityPubResolveInterface;
use Symfony\Component\Lock\Key;

class UpdateActorMessage implements ActivityPubResolveInterface
{
    /**
     * @param ?string $serializedKey serialized Symfony\Component\Lock\Key object with php serialize()
     */
    public function __construct(
        public string $actorUrl,
        public bool $force = false,
        public ?string $serializedKey = null
    ) {
    }

    // have to do this the weird way as symfony serializer somehow fails to
    // reconstruct the Key object properly

    /**
     * create new message with serializedKey set from the given Key.
     */
    public function withKey(?Key $key)
    {
        $c = clone $this;
        $c->serializedKey = !empty($key) ? serialize($key) : null;

        return $c;
    }

    /**
     * reconstruct Key from serialized key in the message.
     */
    public function retrieveKey(): ?Key
    {
        return !empty($this->serializedKey) ? unserialize($this->serializedKey) : null;
    }
}

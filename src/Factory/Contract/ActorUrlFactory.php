<?php

namespace App\Factory\Contract;

use App\Entity\Contracts\ActivityPubActorInterface;

/**
 * @template T of ActivityPubActorInterface
 */
interface ActorUrlFactory
{

    /**
     * @param T $actor
     * @return string the AP ID globally identifying the activity of $subject
     */
    public function getActivityPubId($actor): string;

    /**
     * @param T $actor
     * @return string the URL on this host to the page showing the subject
     */
    public function getLocalUrl($actor): string;

    /**
     * @param T $actor
     * @return string|null the (possible relative) URL on this host to the avatar image (if the actor has one)
     */
    public function getAvatarUrl($actor): ?string;
}

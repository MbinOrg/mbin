<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait FollowActivityGeneratorTrait
{
    public function getFollowUserActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');

        return $this->followWrapper->build($user2, $this->user);
    }

    public function getAcceptFollowUserActivity(): Activity
    {
        return $this->followResponseWrapper->build($this->user, $this->getFollowUserActivity());
    }

    public function getRejectFollowUserActivity(): Activity
    {
        return $this->followResponseWrapper->build($this->user, $this->getFollowUserActivity(), isReject: true);
    }

    public function getFollowMagazineActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');

        return $this->followWrapper->build($user2, $this->magazine);
    }

    public function getAcceptFollowMagazineActivity(): Activity
    {
        return $this->followResponseWrapper->build($this->magazine, $this->getFollowMagazineActivity());
    }

    public function getRejectFollowMagazineActivity(): Activity
    {
        return $this->followResponseWrapper->build($this->magazine, $this->getFollowMagazineActivity(), isReject: true);
    }
}

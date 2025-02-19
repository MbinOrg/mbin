<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\Unit\ActivityPub\ActivityPubJsonDriver;
use App\Tests\Unit\ActivityPub\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\FollowActivityGeneratorTrait;

class FollowTest extends ActivityPubTestCase
{
    use FollowActivityGeneratorTrait;

    public function testFollowUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getFollowUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAcceptFollowUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAcceptFollowUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testRejectFollowUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getRejectFollowUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAcceptFollowMagazine(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAcceptFollowMagazineActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testRejectFollowMagazine(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getRejectFollowMagazineActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\Unit\ActivityPub\ActivityPubJsonDriver;
use App\Tests\Unit\ActivityPub\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\LikeActivityGeneratorTrait;

class LikeTest extends ActivityPubTestCase
{
    use LikeActivityGeneratorTrait;

    public function testLikeEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikeEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLikeEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikeEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLikeNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikeNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLikePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLikePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLikeNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLikeNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

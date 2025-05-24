<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\FollowActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\LikeActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\UndoActivityGeneratorTrait;

class UndoTest extends ActivityPubTestCase
{
    use FollowActivityGeneratorTrait;
    use LikeActivityGeneratorTrait;
    use UndoActivityGeneratorTrait;

    public function testUndoLikeEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikeEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoLikeEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikeEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoLikeNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikeNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoLikePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoLikePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoLikeNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoLikeNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoFollowUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoFollowUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUndoFollowMagazine(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUndoFollowMagazineActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

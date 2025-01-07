<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\Unit\ActivityPub\ActivityPubJsonDriver;
use App\Tests\Unit\ActivityPub\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\DeleteActivityGeneratorTrait;

class DeleteTest extends ActivityPubTestCase
{
    use DeleteActivityGeneratorTrait;

    public function testDeleteUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeleteUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeleteEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeleteEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeleteEntryByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeleteEntryByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeleteEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeleteEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeleteEntryCommentByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeleteEntryCommentByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeletePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeletePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeletePostByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeletePostByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeletePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeletePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testDeletePostCommentByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getDeletePostCommentByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

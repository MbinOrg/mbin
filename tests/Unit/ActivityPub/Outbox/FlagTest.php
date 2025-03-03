<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\FlagActivityGeneratorTrait;

class FlagTest extends ActivityPubTestCase
{
    use FlagActivityGeneratorTrait;

    public function testFlagEntry(): void
    {
        $activity = $this->getFlagEntryActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testFlagEntryComment(): void
    {
        $activity = $this->getFlagEntryCommentActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testFlagNestedEntryComment(): void
    {
        $activity = $this->getFlagNestedEntryCommentActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testFlagPost(): void
    {
        $activity = $this->getFlagPostActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testFlagPostComment(): void
    {
        $activity = $this->getFlagPostCommentActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testFlagNestedPostComment(): void
    {
        $activity = $this->getFlagNestedPostCommentActivity($this->getUserByUsername('reportingUser'));
        $json = $this->activityJsonBuilder->buildActivityJson($activity);

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

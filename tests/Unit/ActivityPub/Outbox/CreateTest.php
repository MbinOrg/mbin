<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\CreateActivityGeneratorTrait;

class CreateTest extends ActivityPubTestCase
{
    use CreateActivityGeneratorTrait;

    public function testCreateEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryWithUrlAndImage(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryActivityWithImageAndUrl());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateMessage(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateMessageActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

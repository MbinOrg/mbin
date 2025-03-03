<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\UpdateActivityGeneratorTrait;

class UpdateTest extends ActivityPubTestCase
{
    use UpdateActivityGeneratorTrait;

    public function testUpdateUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdateUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUpdateMagazine(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdateMagazineActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUpdateEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdateEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUpdateEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdateEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUpdatePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdatePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUpdatePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUpdatePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\AddRemoveActivityGeneratorTrait;

class AddHandlerTest extends ActivityPubTestCase
{
    use AddRemoveActivityGeneratorTrait;

    public function testAddModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAddModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testRemoveModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getRemoveModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAddPinnedPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAddPinnedPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testRemovePinnedPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getRemovePinnedPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

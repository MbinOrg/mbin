<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\LockActivityGeneratorTrait;

class LockTest extends ActivityPubTestCase
{
    use LockActivityGeneratorTrait;

    public function testLockEntryByAuthor(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLockEntryActivityByAuthor());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLockEntryByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLockEntryActivityByModerator());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLockPostByAuthor(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLockPostActivityByAuthor());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testLockPostByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getLockPostActivityByModerator());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

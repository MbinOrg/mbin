<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\BlockActivityGeneratorTrait;

class BlockTest extends ActivityPubTestCase
{
    use BlockActivityGeneratorTrait;

    public function testBlockUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getBlockUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

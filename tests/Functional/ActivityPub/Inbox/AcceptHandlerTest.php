<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class AcceptHandlerTest extends ActivityPubFunctionalTestCase
{
    public function setUpRemoteEntities(): void
    {
        // TODO: Implement setUpRemoteEntities() method.
    }
}

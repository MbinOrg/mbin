<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait BlockActivityGeneratorTrait
{
    public function getBlockUserActivity(): Activity
    {
        $ban = $this->magazine->addBan($this->user, $this->owner, 'some test', null);

        return $this->blockFactory->createActivityFromMagazineBan($ban);
    }
}

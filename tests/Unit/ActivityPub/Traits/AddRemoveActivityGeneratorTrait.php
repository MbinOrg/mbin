<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait AddRemoveActivityGeneratorTrait
{
    public function getAddModeratorActivity(): Activity
    {
        return $this->addRemoveFactory->buildAddModerator($this->owner, $this->user, $this->magazine);
    }

    public function getRemoveModeratorActivity(): Activity
    {
        return $this->addRemoveFactory->buildRemoveModerator($this->owner, $this->user, $this->magazine);
    }

    public function getAddPinnedPostActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->addRemoveFactory->buildAddPinnedPost($this->owner, $entry);
    }

    public function getRemovePinnedPostActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->addRemoveFactory->buildRemovePinnedPost($this->owner, $entry);
    }
}

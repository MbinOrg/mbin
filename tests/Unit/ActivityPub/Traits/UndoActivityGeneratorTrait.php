<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait UndoActivityGeneratorTrait
{
    public function getUndoLikeEntryActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikeEntryActivity());
    }

    public function getUndoLikeEntryCommentActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikeEntryCommentActivity());
    }

    public function getUndoLikeNestedEntryCommentActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikeNestedEntryCommentActivity());
    }

    public function getUndoLikePostActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikePostActivity());
    }

    public function getUndoLikePostCommentActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikePostCommentActivity());
    }

    public function getUndoLikeNestedPostCommentActivity(): Activity
    {
        return $this->undoWrapper->build($this->getLikeNestedPostCommentActivity());
    }

    public function getUndoFollowUserActivity(): Activity
    {
        return $this->undoWrapper->build($this->getFollowUserActivity());
    }

    public function getUndoFollowMagazineActivity(): Activity
    {
        return $this->undoWrapper->build($this->getFollowMagazineActivity());
    }
}

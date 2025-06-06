<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait UpdateActivityGeneratorTrait
{
    public function getUpdateUserActivity(): Activity
    {
        return $this->updateWrapper->buildForActor($this->user);
    }

    public function getUpdateMagazineActivity(): Activity
    {
        return $this->updateWrapper->buildForActor($this->magazine, editedBy: $this->owner);
    }

    public function getUpdateEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->updateWrapper->buildForActivity($entry);
    }

    public function getUpdateEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->updateWrapper->buildForActivity($entryComment);
    }

    public function getUpdatePostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->updateWrapper->buildForActivity($post);
    }

    public function getUpdatePostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->updateWrapper->buildForActivity($postComment);
    }
}

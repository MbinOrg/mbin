<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait DeleteActivityGeneratorTrait
{
    public function getDeleteUserActivity(): Activity
    {
        return $this->deleteWrapper->buildForUser($this->user);
    }

    public function getDeleteEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->user, $entry);
    }

    public function getDeleteEntryByModeratorActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->owner, $entry);
    }

    public function getDeleteEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->user, $entryComment);
    }

    public function getDeleteEntryCommentByModeratorActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->owner, $entryComment);
    }

    public function getDeletePostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->user, $post);
    }

    public function getDeletePostByModeratorActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->owner, $post);
    }

    public function getDeletePostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->user, $postComment);
    }

    public function getDeletePostCommentByModeratorActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->deleteWrapper->adjustDeletePayload($this->owner, $postComment);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait LikeActivityGeneratorTrait
{
    public function getLikeEntryActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->likeWrapper->build($user2, $entry);
    }

    public function getLikeEntryCommentActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->likeWrapper->build($user2, $entryComment);
    }

    public function getLikeNestedEntryCommentActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        return $this->likeWrapper->build($user2, $entryComment2);
    }

    public function getLikePostActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->likeWrapper->build($user2, $post);
    }

    public function getLikePostCommentActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->likeWrapper->build($user2, $postComment);
    }

    public function getLikeNestedPostCommentActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        return $this->likeWrapper->build($user2, $postComment2);
    }
}

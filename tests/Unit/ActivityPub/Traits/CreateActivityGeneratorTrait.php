<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait CreateActivityGeneratorTrait
{
    public function getCreateEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->createWrapper->build($entry);
    }

    public function getCreateEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->createWrapper->build($entryComment);
    }

    public function getCreateNestedEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        return $this->createWrapper->build($entryComment2);
    }

    public function getCreatePostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->createWrapper->build($post);
    }

    public function getCreatePostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->createWrapper->build($postComment);
    }

    public function getCreateNestedPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        return $this->createWrapper->build($postComment2);
    }

    public function getCreateMessageActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $message = $this->createMessage($user2, $this->user, 'some test message');

        return $this->createWrapper->build($message);
    }
}

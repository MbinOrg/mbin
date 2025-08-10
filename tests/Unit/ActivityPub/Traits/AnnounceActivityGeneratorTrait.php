<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;

trait AnnounceActivityGeneratorTrait
{
    public function getAnnounceAddModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getAddModeratorActivity());
    }

    public function getAnnounceRemoveModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getRemoveModeratorActivity());
    }

    public function getAnnounceAddPinnedPostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getAddPinnedPostActivity());
    }

    public function getAnnounceRemovePinnedPostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getRemovePinnedPostActivity());
    }

    public function getAnnounceCreateEntryActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreateEntryActivity());
    }

    public function getAnnounceCreateEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreateEntryCommentActivity());
    }

    public function getAnnounceCreateNestedEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreateNestedEntryCommentActivity());
    }

    public function getAnnounceCreatePostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreatePostActivity());
    }

    public function getAnnounceCreatePostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreatePostCommentActivity());
    }

    public function getAnnounceCreateNestedPostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreateNestedPostCommentActivity());
    }

    public function getAnnounceCreateMessageActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getCreateMessageActivity());
    }

    public function getAnnounceDeleteUserActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeleteUserActivity());
    }

    public function getAnnounceDeleteEntryActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeleteEntryActivity());
    }

    public function getAnnounceDeleteEntryByModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeleteEntryByModeratorActivity());
    }

    public function getAnnounceDeleteEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeleteEntryCommentActivity());
    }

    public function getAnnounceDeleteEntryCommentByModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeleteEntryCommentByModeratorActivity());
    }

    public function getAnnounceDeletePostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeletePostActivity());
    }

    public function getAnnounceDeletePostByModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeletePostByModeratorActivity());
    }

    public function getAnnounceDeletePostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeletePostCommentActivity());
    }

    public function getAnnounceDeletePostCommentByModeratorActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getDeletePostCommentByModeratorActivity());
    }

    public function getUserBoostEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->announceWrapper->build($this->user, $entry, true);
    }

    public function getMagazineBoostEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->announceWrapper->build($this->magazine, $entry, true);
    }

    public function getUserBoostEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->announceWrapper->build($this->user, $entryComment, true);
    }

    public function getMagazineBoostEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->announceWrapper->build($this->magazine, $entryComment, true);
    }

    public function getUserBoostNestedEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        return $this->announceWrapper->build($this->user, $entryComment2, true);
    }

    public function getMagazineBoostNestedEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        return $this->announceWrapper->build($this->magazine, $entryComment2, true);
    }

    public function getUserBoostPostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->announceWrapper->build($this->user, $post, true);
    }

    public function getMagazineBoostPostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->announceWrapper->build($this->magazine, $post, true);
    }

    public function getUserBoostPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->announceWrapper->build($this->user, $postComment, true);
    }

    public function getMagazineBoostPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->announceWrapper->build($this->magazine, $postComment, true);
    }

    public function getUserBoostNestedPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        return $this->announceWrapper->build($this->user, $postComment2, true);
    }

    public function getMagazineBoostNestedPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        return $this->announceWrapper->build($this->magazine, $postComment2, true);
    }

    public function getAnnounceLikeEntryActivity(): Activity
    {
        $like = $this->getLikeEntryActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceLikeEntryCommentActivity(): Activity
    {
        $like = $this->getLikeEntryCommentActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceLikeNestedEntryCommentActivity(): Activity
    {
        $like = $this->getLikeNestedEntryCommentActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceLikePostActivity(): Activity
    {
        $like = $this->getLikePostActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceLikePostCommentActivity(): Activity
    {
        $like = $this->getLikePostCommentActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceLikeNestedPostCommentActivity(): Activity
    {
        $like = $this->getLikeNestedPostCommentActivity();

        return $this->announceWrapper->build($this->magazine, $like, true);
    }

    public function getAnnounceUndoLikeEntryActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikeEntryActivity());
    }

    public function getAnnounceUndoLikeEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikeEntryCommentActivity());
    }

    public function getAnnounceUndoLikeNestedEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikeNestedEntryCommentActivity());
    }

    public function getAnnounceUndoLikePostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikePostActivity());
    }

    public function getAnnounceUndoLikePostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikePostCommentActivity());
    }

    public function getAnnounceUndoLikeNestedPostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoLikeNestedPostCommentActivity());
    }

    public function getAnnounceUpdateUserActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdateUserActivity());
    }

    public function getAnnounceUpdateMagazineActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdateMagazineActivity());
    }

    public function getAnnounceUpdateEntryActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdateEntryActivity());
    }

    public function getAnnounceUpdateEntryCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdateEntryCommentActivity());
    }

    public function getAnnounceUpdatePostActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdatePostActivity());
    }

    public function getAnnounceUpdatePostCommentActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUpdatePostCommentActivity());
    }

    public function getAnnounceBlockUserActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getBlockUserActivity());
    }

    public function getAnnounceUndoBlockUserActivity(): Activity
    {
        return $this->announceWrapper->build($this->magazine, $this->getUndoBlockUserActivity());
    }
}

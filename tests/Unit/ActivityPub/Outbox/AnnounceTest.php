<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\AddRemoveActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\AnnounceActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\BlockActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\CreateActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\DeleteActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\FollowActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\LikeActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\UndoActivityGeneratorTrait;
use App\Tests\Unit\ActivityPub\Traits\UpdateActivityGeneratorTrait;

class AnnounceTest extends ActivityPubTestCase
{
    use AddRemoveActivityGeneratorTrait;
    use AnnounceActivityGeneratorTrait;
    use LikeActivityGeneratorTrait;
    use FollowActivityGeneratorTrait;
    use CreateActivityGeneratorTrait;
    use DeleteActivityGeneratorTrait;
    use UndoActivityGeneratorTrait;
    use UpdateActivityGeneratorTrait;
    use BlockActivityGeneratorTrait;

    public function testAnnounceAddModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceAddModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceRemoveModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceRemoveModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceAddPinnedPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceAddPinnedPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceRemovePinnedPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceRemovePinnedPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreateEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreateEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreateEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreateEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreateNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreateNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreatePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreatePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreatePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreatePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreateNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreateNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceCreateMessage(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceCreateMessageActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeleteUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeleteUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeleteEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeleteEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeleteEntryByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeleteEntryByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeleteEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeleteEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeleteEntryCommentByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeleteEntryCommentByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeletePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeletePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeletePostByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeletePostByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeletePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeletePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceDeletePostCommentByModerator(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceDeletePostCommentByModeratorActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostPost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostPostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testUserBoostNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getUserBoostNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testMagazineBoostNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getMagazineBoostNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikeEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikeEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikeEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikeEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikeNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikeNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceLikeNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceLikeNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikeEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikeEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikeEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikeEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikeNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikeNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoLikeNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoLikeNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdateUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdateUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdateMagazine(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdateMagazineActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdateEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdateEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdateEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdateEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdatePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdatePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUpdatePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUpdatePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceBlockUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceBlockUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testAnnounceUndoBlockUser(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getAnnounceUndoBlockUserActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Outbox;

use App\Tests\ActivityPubJsonDriver;
use App\Tests\ActivityPubTestCase;
use App\Tests\Unit\ActivityPub\Traits\CreateActivityGeneratorTrait;

class CreateTest extends ActivityPubTestCase
{
    use CreateActivityGeneratorTrait;

    public function testCreateEntry(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryWithUrlAndImage(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryActivityWithImageAndUrl());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryWithPollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryWithMultipleChoicePollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryCommentWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryCommentWithPollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateEntryCommentWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateEntryCommentWithMultipleChoicePollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedEntryComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedEntryCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedEntryCommentWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedEntryCommentWithPollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedEntryCommentWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedEntryCommentWithMultipleChoicePollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePost(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostActivityWithPoll());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostActivityWithMultipleChoicePoll());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostCommentWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostCommentActivityWithPoll());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePostCommentWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePostCommentActivityWithMultipleChoicePoll());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedPostComment(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedPostCommentActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedPostCommentWithPoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedPostCommentWithPollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateNestedPostCommentWithMultipleChoicePoll(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateNestedPostCommentWithMultipleChoicePollActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreateMessage(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreateMessageActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }

    public function testCreatePollVote(): void
    {
        $json = $this->activityJsonBuilder->buildActivityJson($this->getCreatePollVoteActivity());

        $this->assertMatchesSnapshot($json, new ActivityPubJsonDriver());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\ActivityPub\Traits;

use App\Entity\Activity;
use App\Entity\PollVote;

trait CreateActivityGeneratorTrait
{
    public function getCreateEntryActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);

        return $this->createWrapper->build($entry);
    }

    public function getCreateEntryWithPollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entry->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($entry);
    }

    public function getCreateEntryWithMultipleChoicePollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entry->poll = $this->createSimplePoll(true, true);

        return $this->createWrapper->build($entry);
    }

    public function getCreateEntryActivityWithImageAndUrl(): Activity
    {
        $entry = $this->getEntryByTitle('test', url: 'https://joinmbin.org', magazine: $this->magazine, user: $this->user, image: $this->getKibbyImageDto());

        return $this->createWrapper->build($entry);
    }

    public function getCreateEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);

        return $this->createWrapper->build($entryComment);
    }

    public function getCreateEntryCommentWithPollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($entryComment);
    }

    public function getCreateEntryCommentWithMultipleChoicePollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($entryComment);
    }

    public function getCreateNestedEntryCommentActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);

        return $this->createWrapper->build($entryComment2);
    }

    public function getCreateNestedEntryCommentWithPollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);
        $entryComment2->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($entryComment2);
    }

    public function getCreateNestedEntryCommentWithMultipleChoicePollActivity(): Activity
    {
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entryComment = $this->createEntryComment('test', entry: $entry, user: $this->user);
        $entryComment2 = $this->createEntryComment('test', entry: $entry, user: $this->user, parent: $entryComment);
        $entryComment2->poll = $this->createSimplePoll(true, true);

        return $this->createWrapper->build($entryComment2);
    }

    public function getCreatePostActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);

        return $this->createWrapper->build($post);
    }

    public function getCreatePostActivityWithPoll(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $post->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($post);
    }

    public function getCreatePostActivityWithMultipleChoicePoll(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $post->poll = $this->createSimplePoll(true, true);

        return $this->createWrapper->build($post);
    }

    public function getCreatePostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);

        return $this->createWrapper->build($postComment);
    }

    public function getCreatePostCommentActivityWithPoll(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($postComment);
    }

    public function getCreatePostCommentActivityWithMultipleChoicePoll(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment->poll = $this->createSimplePoll(true, true);

        return $this->createWrapper->build($postComment);
    }

    public function getCreateNestedPostCommentActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);

        return $this->createWrapper->build($postComment2);
    }

    public function getCreateNestedPostCommentWithPollActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);
        $postComment2->poll = $this->createSimplePoll(false, true);

        return $this->createWrapper->build($postComment2);
    }

    public function getCreateNestedPostCommentWithMultipleChoicePollActivity(): Activity
    {
        $post = $this->createPost('test', magazine: $this->magazine, user: $this->user);
        $postComment = $this->createPostComment('test', post: $post, user: $this->user);
        $postComment2 = $this->createPostComment('test', post: $post, user: $this->user, parent: $postComment);
        $postComment2->poll = $this->createSimplePoll(true, true);

        return $this->createWrapper->build($postComment2);
    }

    public function getCreateMessageActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $message = $this->createMessage($user2, $this->user, 'some test message');

        return $this->createWrapper->build($message);
    }

    public function getCreatePollVoteActivity(): Activity
    {
        $user2 = $this->getUserByUsername('user2');
        $entry = $this->getEntryByTitle('test', magazine: $this->magazine, user: $this->user);
        $entry->poll = $this->createSimplePoll(false, false);

        $vote = new PollVote();
        $vote->poll = $entry->poll;
        $vote->choice = $entry->poll->findChoice('A');
        $vote->voter = $user2;
        $this->entityManager->persist($vote);

        return $this->createWrapper->build($vote);
    }
}

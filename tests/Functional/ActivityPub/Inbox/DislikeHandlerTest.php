<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class DislikeHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $announceEntry;
    private array $dislikeAnnounceEntry;
    private array $announceEntryComment;
    private array $dislikeAnnounceEntryComment;
    private array $announcePost;
    private array $dislikeAnnouncePost;
    private array $announcePostComment;
    private array $dislikeAnnouncePostComment;
    private array $createEntry;
    private array $dislikeCreateEntry;
    private array $createEntryComment;
    private array $dislikeCreateEntryComment;
    private array $createPost;
    private array $dislikeCreatePost;
    private array $createPostComment;
    private array $dislikeCreatePostComment;

    public function testDislikeRemoteEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertSame(0, $entry->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeAnnounceEntry)));
        $this->entityManager->refresh($entry);
        self::assertNotNull($entry);
        self::assertSame(1, $entry->countDownVotes());
    }

    public function testDislikeRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntryComment)));
        $comment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertSame(0, $comment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeAnnounceEntryComment)));
        $this->entityManager->refresh($comment);
        self::assertNotNull($comment);
        self::assertSame(1, $comment->countDownVotes());
    }

    public function testDislikeRemotePostInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertSame(0, $post->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeAnnouncePost)));
        $this->entityManager->refresh($post);
        self::assertNotNull($post);
        self::assertSame(1, $post->countDownVotes());
    }

    public function testDislikeRemotePostCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertSame(0, $postComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeAnnouncePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertNotNull($postComment);
        self::assertSame(1, $postComment->countDownVotes());
    }

    public function testDislikeEntryInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertSame(0, $entry->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeCreateEntry)));
        $this->entityManager->refresh($entry);
        self::assertSame(1, $entry->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Dislike' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Dislike' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->dislikeCreateEntry['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->dislikeCreateEntry['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    public function testDislikeEntryCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryComment)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertSame(0, $entryComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeCreateEntryComment)));
        $this->entityManager->refresh($entryComment);
        self::assertSame(1, $entryComment->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Dislike' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Dislike' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->dislikeCreateEntryComment['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->dislikeCreateEntryComment['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    public function testDislikePostInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        self::assertSame(0, $post->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeCreatePost)));
        $this->entityManager->refresh($post);
        self::assertSame(1, $post->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Dislike' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Dislike' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->dislikeCreatePost['id'], $postedUpdateAnnounce['payload']['object']['id']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->dislikeCreatePost['object'], $postedUpdateAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    public function testDislikePostCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertSame(0, $postComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->dislikeCreatePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertSame(1, $postComment->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Dislike' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Dislike' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->dislikeCreatePostComment['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->dislikeCreatePostComment['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    public function setUpRemoteEntities(): void
    {
        $this->announceEntry = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildDislikeRemoteEntryInRemoteMagazine($entry));
        $this->announceEntryComment = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildDislikeRemoteEntryCommentInRemoteMagazine($comment));
        $this->announcePost = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Post $post) => $this->buildDislikeRemotePostInRemoteMagazine($post));
        $this->announcePostComment = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildDislikeRemotePostCommentInRemoteMagazine($comment));
        $this->createEntry = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildDislikeRemoteEntryInLocalMagazine($entry));
        $this->createEntryComment = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildDislikeRemoteEntryCommentInLocalMagazine($comment));
        $this->createPost = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Post $post) => $this->buildDislikeRemotePostInLocalMagazine($post));
        $this->createPostComment = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildDislikeRemotePostCommentInLocalMagazine($comment));
    }

    public function buildDislikeRemoteEntryInRemoteMagazine(Entry $entry): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $entry);
        $this->dislikeAnnounceEntry = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnounceEntry['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnounceEntry['id']] = $this->dislikeAnnounceEntry;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->dislikeAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($updateActivity);
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnounceEntryComment['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnounceEntryComment['id']] = $this->dislikeAnnounceEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemotePostInRemoteMagazine(Post $post): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->dislikeAnnouncePost = $this->activityJsonBuilder->buildActivityJson($updateActivity);
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnouncePost['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnouncePost['id']] = $this->dislikeAnnouncePost;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemotePostCommentInRemoteMagazine(PostComment $postComment): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->dislikeAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($updateActivity);
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnouncePostComment['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnouncePostComment['id']] = $this->dislikeAnnouncePostComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemoteEntryInLocalMagazine(Entry $entry): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $entry);
        $this->dislikeCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreateEntry['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreateEntry['id']] = $this->dislikeCreateEntry;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->dislikeCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreateEntryComment['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreateEntryComment['id']] = $this->dislikeCreateEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemotePostInLocalMagazine(Post $post): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->dislikeCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreatePost['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreatePost['id']] = $this->dislikeCreatePost;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildDislikeRemotePostCommentInLocalMagazine(PostComment $postComment): void
    {
        $updateActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->dislikeCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreatePostComment['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreatePostComment['id']] = $this->dislikeCreatePostComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }
}

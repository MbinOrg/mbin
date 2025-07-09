<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class DislikeHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $announceEntry;
    private array $dislikeAnnounceEntry;
    private array $undoDislikeAnnounceEntry;
    private array $announceEntryComment;
    private array $dislikeAnnounceEntryComment;
    private array $undoDislikeAnnounceEntryComment;
    private array $announcePost;
    private array $dislikeAnnouncePost;
    private array $undoDislikeAnnouncePost;
    private array $announcePostComment;
    private array $dislikeAnnouncePostComment;
    private array $undoDislikeAnnouncePostComment;
    private array $createEntry;
    private array $dislikeCreateEntry;
    private array $undoDislikeCreateEntry;
    private array $createEntryComment;
    private array $dislikeCreateEntryComment;
    private array $undoDislikeCreateEntryComment;
    private array $createPost;
    private array $dislikeCreatePost;
    private array $undoDislikeCreatePost;
    private array $createPostComment;
    private array $dislikeCreatePostComment;
    private array $undoDislikeCreatePostComment;

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

    #[Depends('testDislikeRemoteEntryInRemoteMagazine')]
    public function testUndoDislikeRemoteEntryInRemoteMagazine(): void
    {
        $this->testDislikeRemoteEntryInRemoteMagazine();
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertSame(1, $entry->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeAnnounceEntry)));
        $this->entityManager->refresh($entry);
        self::assertNotNull($entry);
        self::assertSame(0, $entry->countDownVotes());
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

    #[Depends('testDislikeRemoteEntryCommentInRemoteMagazine')]
    public function testUndoLikeRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->testDislikeRemoteEntryCommentInRemoteMagazine();
        $comment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertSame(1, $comment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeAnnounceEntryComment)));
        $this->entityManager->refresh($comment);
        self::assertNotNull($comment);
        self::assertSame(0, $comment->countDownVotes());
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

    #[Depends('testDislikeRemotePostInRemoteMagazine')]
    public function testUndoLikeRemotePostInRemoteMagazine(): void
    {
        $this->testDislikeRemotePostInRemoteMagazine();
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertSame(1, $post->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeAnnouncePost)));
        $this->entityManager->refresh($post);
        self::assertNotNull($post);
        self::assertSame(0, $post->countDownVotes());
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

    #[Depends('testDislikeRemotePostCommentInRemoteMagazine')]
    public function testUndoLikeRemotePostCommentInRemoteMagazine(): void
    {
        $this->testDislikeRemotePostCommentInRemoteMagazine();
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertSame(1, $postComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeAnnouncePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertNotNull($postComment);
        self::assertSame(0, $postComment->countDownVotes());
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

    #[Depends('testDislikeEntryInLocalMagazine')]
    public function testUndoLikeEntryInLocalMagazine(): void
    {
        $this->testDislikeEntryInLocalMagazine();
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertSame(1, $entry->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeCreateEntry)));
        $this->entityManager->refresh($entry);
        self::assertSame(0, $entry->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Dislike' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoDislikeCreateEntry['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Dislike' activity as the object
        self::assertEquals($this->undoDislikeCreateEntry['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->undoDislikeCreateEntry['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
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

    #[Depends('testDislikeEntryCommentInLocalMagazine')]
    public function testUndoLikeEntryCommentInLocalMagazine(): void
    {
        $this->testDislikeEntryCommentInLocalMagazine();
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertSame(1, $entryComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeCreateEntryComment)));
        $this->entityManager->refresh($entryComment);
        self::assertSame(0, $entryComment->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Dislike' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoDislikeCreateEntryComment['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Dislike' activity as the object
        self::assertEquals($this->undoDislikeCreateEntryComment['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->undoDislikeCreateEntryComment['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
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

    #[Depends('testDislikePostInLocalMagazine')]
    public function testUndoLikePostInLocalMagazine(): void
    {
        $this->testDislikePostInLocalMagazine();
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        self::assertSame(1, $post->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeCreatePost)));
        $this->entityManager->refresh($post);
        self::assertSame(0, $post->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Dislike' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoDislikeCreatePost['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Dislike' activity as the object
        self::assertEquals($this->undoDislikeCreatePost['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->undoDislikeCreatePost['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
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

    #[Depends('testDislikePostCommentInLocalMagazine')]
    public function testUndoLikePostCommentInLocalMagazine(): void
    {
        $this->testDislikePostCommentInLocalMagazine();
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertSame(1, $postComment->countDownVotes());
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoDislikeCreatePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertSame(0, $postComment->countDownVotes());

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Dislike' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoDislikeCreatePostComment['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Dislike' activity as the object
        self::assertEquals($this->undoDislikeCreatePostComment['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Dislike' activity has the url as the object
        self::assertEquals($this->undoDislikeCreatePostComment['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
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
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeAnnounceEntry = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnounceEntry['type'] = 'Dislike';
        $this->undoDislikeAnnounceEntry['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnounceEntry['id']] = $this->dislikeAnnounceEntry;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->dislikeAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnounceEntryComment['type'] = 'Dislike';
        $this->undoDislikeAnnounceEntryComment['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnounceEntryComment['id']] = $this->dislikeAnnounceEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemotePostInRemoteMagazine(Post $post): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->dislikeAnnouncePost = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeAnnouncePost = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnouncePost['type'] = 'Dislike';
        $this->undoDislikeAnnouncePost['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnouncePost['id']] = $this->dislikeAnnouncePost;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemotePostCommentInRemoteMagazine(PostComment $postComment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->dislikeAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeAnnouncePostComment['type'] = 'Dislike';
        $this->undoDislikeAnnouncePostComment['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeAnnouncePostComment['id']] = $this->dislikeAnnouncePostComment;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemoteEntryInLocalMagazine(Entry $entry): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $entry);
        $this->dislikeCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreateEntry['type'] = 'Dislike';
        $this->undoDislikeCreateEntry['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreateEntry['id']] = $this->dislikeCreateEntry;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->dislikeCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreateEntryComment['type'] = 'Dislike';
        $this->undoDislikeCreateEntryComment['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreateEntryComment['id']] = $this->dislikeCreateEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemotePostInLocalMagazine(Post $post): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->dislikeCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreatePost['type'] = 'Dislike';
        $this->undoDislikeCreatePost['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreatePost['id']] = $this->dislikeCreatePost;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }

    public function buildDislikeRemotePostCommentInLocalMagazine(PostComment $postComment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->dislikeCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity);
        $this->undoDislikeCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        // since we do not have outgoing federation of dislikes we cheat that here so we can test our inbox federation
        $this->dislikeCreatePostComment['type'] = 'Dislike';
        $this->undoDislikeCreatePostComment['object']['type'] = 'Dislike';

        $this->testingApHttpClient->activityObjects[$this->dislikeCreatePostComment['id']] = $this->dislikeCreatePostComment;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
    }
}

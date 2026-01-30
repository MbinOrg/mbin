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
class LikeHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $announceEntry;
    private array $likeAnnounceEntry;
    private array $undoLikeAnnounceEntry;
    private array $announceEntryComment;
    private array $likeAnnounceEntryComment;
    private array $undoLikeAnnounceEntryComment;
    private array $announcePost;
    private array $likeAnnouncePost;
    private array $undoLikeAnnouncePost;
    private array $announcePostComment;
    private array $likeAnnouncePostComment;
    private array $undoLikeAnnouncePostComment;
    private array $createEntry;
    private array $likeCreateEntry;
    private array $undoLikeCreateEntry;
    private array $createEntryComment;
    private array $likeCreateEntryComment;
    private array $undoLikeCreateEntryComment;
    private array $createPost;
    private array $likeCreatePost;
    private array $undoLikeCreatePost;
    private array $createPostComment;
    private array $likeCreatePostComment;
    private array $undoLikeCreatePostComment;

    public function testLikeRemoteEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertSame(0, $entry->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeAnnounceEntry)));
        $this->entityManager->refresh($entry);
        self::assertNotNull($entry);
        self::assertSame(1, $entry->favouriteCount);
    }

    #[Depends('testLikeRemoteEntryInRemoteMagazine')]
    public function testUndoLikeRemoteEntryInRemoteMagazine(): void
    {
        $this->testLikeRemoteEntryInRemoteMagazine();
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertSame(1, $entry->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeAnnounceEntry)));
        $this->entityManager->refresh($entry);
        self::assertNotNull($entry);
        self::assertSame(0, $entry->favouriteCount);
    }

    public function testLikeRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntryComment)));
        $comment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertSame(0, $comment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeAnnounceEntryComment)));
        $this->entityManager->refresh($comment);
        self::assertNotNull($comment);
        self::assertSame(1, $comment->favouriteCount);
    }

    #[Depends('testLikeRemoteEntryCommentInRemoteMagazine')]
    public function testUndoLikeRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->testLikeRemoteEntryCommentInRemoteMagazine();
        $comment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertSame(1, $comment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeAnnounceEntryComment)));
        $this->entityManager->refresh($comment);
        self::assertNotNull($comment);
        self::assertSame(0, $comment->favouriteCount);
    }

    public function testLikeRemotePostInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertSame(0, $post->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeAnnouncePost)));
        $this->entityManager->refresh($post);
        self::assertNotNull($post);
        self::assertSame(1, $post->favouriteCount);
    }

    #[Depends('testLikeRemotePostInRemoteMagazine')]
    public function testUndoLikeRemotePostInRemoteMagazine(): void
    {
        $this->testLikeRemotePostInRemoteMagazine();
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertSame(1, $post->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeAnnouncePost)));
        $this->entityManager->refresh($post);
        self::assertNotNull($post);
        self::assertSame(0, $post->favouriteCount);
    }

    public function testLikeRemotePostCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertSame(0, $postComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeAnnouncePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertNotNull($postComment);
        self::assertSame(1, $postComment->favouriteCount);
    }

    #[Depends('testLikeRemotePostCommentInRemoteMagazine')]
    public function testUndoLikeRemotePostCommentInRemoteMagazine(): void
    {
        $this->testLikeRemotePostCommentInRemoteMagazine();
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertSame(1, $postComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeAnnouncePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertNotNull($postComment);
        self::assertSame(0, $postComment->favouriteCount);
    }

    public function testLikeEntryInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertSame(0, $entry->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeCreateEntry)));
        $this->entityManager->refresh($entry);
        self::assertSame(1, $entry->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Like' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Like' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->likeCreateEntry['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->likeCreateEntry['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    #[Depends('testLikeEntryInLocalMagazine')]
    public function testUndoLikeEntryInLocalMagazine(): void
    {
        $this->testLikeEntryInLocalMagazine();
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertSame(1, $entry->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeCreateEntry)));
        $this->entityManager->refresh($entry);
        self::assertSame(0, $entry->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Like' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoLikeCreateEntry['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Like' activity as the object
        self::assertEquals($this->undoLikeCreateEntry['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->undoLikeCreateEntry['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
    }

    public function testLikeEntryCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryComment)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertSame(0, $entryComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeCreateEntryComment)));
        $this->entityManager->refresh($entryComment);
        self::assertSame(1, $entryComment->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Like' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Like' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->likeCreateEntryComment['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->likeCreateEntryComment['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    #[Depends('testLikeEntryCommentInLocalMagazine')]
    public function testUndoLikeEntryCommentInLocalMagazine(): void
    {
        $this->testLikeEntryCommentInLocalMagazine();
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertSame(1, $entryComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeCreateEntryComment)));
        $this->entityManager->refresh($entryComment);
        self::assertSame(0, $entryComment->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Like' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoLikeCreateEntryComment['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Like' activity as the object
        self::assertEquals($this->undoLikeCreateEntryComment['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->undoLikeCreateEntryComment['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
    }

    public function testLikePostInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        self::assertSame(0, $post->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeCreatePost)));
        $this->entityManager->refresh($post);
        self::assertSame(1, $post->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Like' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Like' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->likeCreatePost['id'], $postedUpdateAnnounce['payload']['object']['id']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->likeCreatePost['object'], $postedUpdateAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    #[Depends('testLikePostInLocalMagazine')]
    public function testUndoLikePostInLocalMagazine(): void
    {
        $this->testLikePostInLocalMagazine();
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        self::assertSame(1, $post->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeCreatePost)));
        $this->entityManager->refresh($post);
        self::assertSame(0, $post->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Like' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoLikeCreatePost['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Like' activity as the object
        self::assertEquals($this->undoLikeCreatePost['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->undoLikeCreatePost['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
    }

    public function testLikePostCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertSame(0, $postComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->likeCreatePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertSame(1, $postComment->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Like' === $arr['payload']['object']['type']);
        $postedLikeAnnounce = $postedLikeAnnounces[array_key_first($postedLikeAnnounces)];
        // the id of the 'Like' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->likeCreatePostComment['id'], $postedLikeAnnounce['payload']['object']['id']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->likeCreatePostComment['object'], $postedLikeAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedLikeAnnounce['inboxUrl']);
    }

    #[Depends('testLikePostCommentInLocalMagazine')]
    public function testUndoLikePostCommentInLocalMagazine(): void
    {
        $this->testLikePostCommentInLocalMagazine();
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertSame(1, $postComment->favouriteCount);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLikeCreatePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertSame(0, $postComment->favouriteCount);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        $postedUndoLikeAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Undo' === $arr['payload']['object']['type'] && 'Like' === $arr['payload']['object']['object']['type']);
        $postedUndoLikeAnnounce = $postedUndoLikeAnnounces[array_key_first($postedUndoLikeAnnounces)];
        // the id of the 'Undo' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->undoLikeCreatePostComment['id'], $postedUndoLikeAnnounce['payload']['object']['id']);
        // the 'Undo' activity has the 'Like' activity as the object
        self::assertEquals($this->undoLikeCreatePostComment['object'], $postedUndoLikeAnnounce['payload']['object']['object']);
        // the 'Like' activity has the url as the object
        self::assertEquals($this->undoLikeCreatePostComment['object']['object'], $postedUndoLikeAnnounce['payload']['object']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUndoLikeAnnounce['inboxUrl']);
    }

    public function setUpRemoteEntities(): void
    {
        $this->announceEntry = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildLikeRemoteEntryInRemoteMagazine($entry));
        $this->announceEntryComment = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildLikeRemoteEntryCommentInRemoteMagazine($comment));
        $this->announcePost = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Post $post) => $this->buildLikeRemotePostInRemoteMagazine($post));
        $this->announcePostComment = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildLikeRemotePostCommentInRemoteMagazine($comment));
        $this->createEntry = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildLikeRemoteEntryInLocalMagazine($entry));
        $this->createEntryComment = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildLikeRemoteEntryCommentInLocalMagazine($comment));
        $this->createPost = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Post $post) => $this->buildLikeRemotePostInLocalMagazine($post));
        $this->createPostComment = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildLikeRemotePostCommentInLocalMagazine($comment));
    }

    public function buildLikeRemoteEntryInRemoteMagazine(Entry $entry): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $entry);
        $this->likeAnnounceEntry = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoLikeActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeAnnounceEntry = $this->activityJsonBuilder->buildActivityJson($undoLikeActivity);

        $this->testingApHttpClient->activityObjects[$this->likeAnnounceEntry['id']] = $this->likeAnnounceEntry;
        $this->testingApHttpClient->activityObjects[$this->undoLikeAnnounceEntry['id']] = $this->undoLikeAnnounceEntry;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoLikeActivity;
    }

    public function buildLikeRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->likeAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        $this->testingApHttpClient->activityObjects[$this->likeAnnounceEntryComment['id']] = $this->likeAnnounceEntryComment;
        $this->testingApHttpClient->activityObjects[$this->undoLikeAnnounceEntryComment['id']] = $this->undoLikeAnnounceEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemotePostInRemoteMagazine(Post $post): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->likeAnnouncePost = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeAnnouncePost = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        $this->testingApHttpClient->activityObjects[$this->likeAnnouncePost['id']] = $this->likeAnnouncePost;
        $this->testingApHttpClient->activityObjects[$this->undoLikeAnnouncePost['id']] = $this->undoLikeAnnouncePost;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemotePostCommentInRemoteMagazine(PostComment $postComment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->likeAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($likeActivity);
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        $this->testingApHttpClient->activityObjects[$this->likeAnnouncePostComment['id']] = $this->likeAnnouncePostComment;
        $this->testingApHttpClient->activityObjects[$this->undoLikeAnnouncePostComment['id']] = $this->undoLikeAnnouncePostComment;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemoteEntryInLocalMagazine(Entry $entry): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $entry);
        $this->likeCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        $this->testingApHttpClient->activityObjects[$this->likeCreateEntry['id']] = $this->likeCreateEntry;
        $this->testingApHttpClient->activityObjects[$this->undoLikeCreateEntry['id']] = $this->undoLikeCreateEntry;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $comment);
        $this->likeCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        $this->testingApHttpClient->activityObjects[$this->likeCreateEntryComment['id']] = $this->likeCreateEntryComment;
        $this->testingApHttpClient->activityObjects[$this->undoLikeCreateEntryComment['id']] = $this->undoLikeCreateEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemotePostInLocalMagazine(Post $post): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $post);
        $this->likeCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        $this->testingApHttpClient->activityObjects[$this->likeCreatePost['id']] = $this->likeCreatePost;
        $this->testingApHttpClient->activityObjects[$this->undoLikeCreatePost['id']] = $this->undoLikeCreatePost;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }

    public function buildLikeRemotePostCommentInLocalMagazine(PostComment $postComment): void
    {
        $likeActivity = $this->likeWrapper->build($this->remoteUser, $postComment);
        $this->likeCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($likeActivity));
        $undoActivity = $this->undoWrapper->build($likeActivity, $this->remoteUser);
        $this->undoLikeCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($undoActivity));

        $this->testingApHttpClient->activityObjects[$this->likeCreatePostComment['id']] = $this->likeCreatePostComment;
        $this->testingApHttpClient->activityObjects[$this->undoLikeCreatePostComment['id']] = $this->undoLikeCreatePostComment;
        $this->entitiesToRemoveAfterSetup[] = $likeActivity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
    }
}

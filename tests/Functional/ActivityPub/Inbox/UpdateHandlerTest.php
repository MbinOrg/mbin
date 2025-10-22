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
class UpdateHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $announceEntry;
    private array $updateAnnounceEntry;
    private array $announceEntryComment;
    private array $updateAnnounceEntryComment;
    private array $announcePost;
    private array $updateAnnouncePost;
    private array $announcePostComment;
    private array $updateAnnouncePostComment;
    private array $createEntry;
    private array $updateCreateEntry;
    private array $createEntryComment;
    private array $updateCreateEntryComment;
    private array $createPost;
    private array $updateCreatePost;
    private array $createPostComment;
    private array $updateCreatePostComment;
    private array $updateUser;
    private array $updateMagazine;

    public function testUpdateRemoteEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertStringNotContainsString('update', $entry->title);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateAnnounceEntry)));
        $this->entityManager->refresh($entry);
        self::assertNotNull($entry);
        self::assertStringContainsString('update', $entry->title);
        self::assertStringContainsString('update', $entry->body);
    }

    public function testUpdateRemoteEntryCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntryComment)));
        $comment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertStringNotContainsString('update', $comment->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateAnnounceEntryComment)));
        $this->entityManager->refresh($comment);
        self::assertNotNull($comment);
        self::assertStringContainsString('update', $comment->body);
    }

    public function testUpdateRemotePostInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertStringNotContainsString('update', $post->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateAnnouncePost)));
        $this->entityManager->refresh($post);
        self::assertNotNull($post);
        self::assertStringContainsString('update', $post->body);
    }

    public function testUpdateRemotePostCommentInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertStringNotContainsString('update', $postComment->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateAnnouncePostComment)));
        $this->entityManager->refresh($postComment);
        self::assertNotNull($postComment);
        self::assertStringContainsString('update', $postComment->body);
    }

    public function testUpdateEntryInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertStringNotContainsString('update', $entry->title);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateCreateEntry)));
        self::assertStringContainsString('update', $entry->title);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Update' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Update' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->updateCreateEntry['id'], $postedUpdateAnnounce['payload']['object']['id']);
        self::assertEquals($this->updateCreateEntry['object']['id'], $postedUpdateAnnounce['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    public function testUpdateEntryCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryComment)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertStringNotContainsString('update', $entryComment->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateCreateEntryComment)));
        self::assertStringContainsString('update', $entryComment->body);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Update' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Update' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->updateCreateEntryComment['id'], $postedUpdateAnnounce['payload']['object']['id']);
        self::assertEquals($this->updateCreateEntryComment['object']['id'], $postedUpdateAnnounce['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    public function testUpdatePostInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertStringNotContainsString('update', $post->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateCreatePost)));
        self::assertStringContainsString('update', $post->body);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Update' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Update' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->updateCreatePost['id'], $postedUpdateAnnounce['payload']['object']['id']);
        self::assertEquals($this->updateCreatePost['object']['id'], $postedUpdateAnnounce['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    public function testUpdatePostCommentInLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertStringNotContainsString('update', $postComment->body);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateCreatePostComment)));
        self::assertStringContainsString('update', $postComment->body);

        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedUpdateAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Update' === $arr['payload']['object']['type']);
        $postedUpdateAnnounce = $postedUpdateAnnounces[array_key_first($postedUpdateAnnounces)];
        // the id of the 'Update' activity should be wrapped in an 'Announce' activity
        self::assertEquals($this->updateCreatePostComment['id'], $postedUpdateAnnounce['payload']['object']['id']);
        self::assertEquals($this->updateCreatePostComment['object']['id'], $postedUpdateAnnounce['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedUpdateAnnounce['inboxUrl']);
    }

    public function testUpdateRemoteUser(): void
    {
        // an update activity forces to fetch the remote object again -> rewrite the actor id to the updated object from the activity
        $this->testingApHttpClient->actorObjects[$this->updateUser['object']['id']] = $this->updateUser['object'];
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateUser)));
        $user = $this->userRepository->findOneBy(['apPublicUrl' => $this->updateUser['object']['id']]);
        self::assertNotNull($user);
        self::assertStringContainsString('update', $user->about);
        self::assertNotNull($user->publicKey);
        self::assertStringContainsString('new public key', $user->publicKey);
        self::assertNotNull($user->lastKeyRotationDate);
    }

    public function testUpdateRemoteMagazine(): void
    {
        // an update activity forces to fetch the remote object again -> rewrite the actor id to the updated object from the activity
        $this->testingApHttpClient->actorObjects[$this->updateMagazine['object']['id']] = $this->updateMagazine['object'];
        $this->bus->dispatch(new ActivityMessage(json_encode($this->updateMagazine)));
        $magazine = $this->magazineRepository->findOneBy(['apPublicUrl' => $this->updateMagazine['object']['id']]);
        self::assertNotNull($magazine);
        self::assertStringContainsString('update', $magazine->description);
        self::assertNotNull($magazine->publicKey);
        self::assertStringContainsString('new public key', $magazine->publicKey);
        self::assertNotNull($magazine->lastKeyRotationDate);
    }

    public function setUpRemoteEntities(): void
    {
        $this->announceEntry = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildUpdateRemoteEntryInRemoteMagazine($entry));
        $this->announceEntryComment = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildUpdateRemoteEntryCommentInRemoteMagazine($comment));
        $this->announcePost = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Post $post) => $this->buildUpdateRemotePostInRemoteMagazine($post));
        $this->announcePostComment = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildUpdateRemotePostCommentInRemoteMagazine($comment));
        $this->createEntry = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildUpdateRemoteEntryInLocalMagazine($entry));
        $this->createEntryComment = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (EntryComment $comment) => $this->buildUpdateRemoteEntryCommentInLocalMagazine($comment));
        $this->createPost = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Post $post) => $this->buildUpdateRemotePostInLocalMagazine($post));
        $this->createPostComment = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remoteUser, fn (PostComment $comment) => $this->buildUpdateRemotePostCommentInLocalMagazine($comment));
        $this->buildUpdateUser();
        $this->buildUpdateMagazine();
    }

    public function buildUpdateRemoteEntryInRemoteMagazine(Entry $entry): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($entry, $this->remoteUser);
        $entry->title = 'Some updated title';
        $entry->body = 'Some updated body';
        $this->updateAnnounceEntry = $this->activityJsonBuilder->buildActivityJson($updateActivity);

        $this->testingApHttpClient->activityObjects[$this->updateAnnounceEntry['id']] = $this->updateAnnounceEntry;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($comment, $this->remoteUser);
        $comment->body = 'Some updated body';
        $this->updateAnnounceEntryComment = $this->activityJsonBuilder->buildActivityJson($updateActivity);

        $this->testingApHttpClient->activityObjects[$this->updateAnnounceEntryComment['id']] = $this->updateAnnounceEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemotePostInRemoteMagazine(Post $post): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($post, $this->remoteUser);
        $post->body = 'Some updated body';
        $this->updateAnnouncePost = $this->activityJsonBuilder->buildActivityJson($updateActivity);

        $this->testingApHttpClient->activityObjects[$this->updateAnnouncePost['id']] = $this->updateAnnouncePost;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemotePostCommentInRemoteMagazine(PostComment $postComment): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($postComment, $this->remoteUser);
        $postComment->body = 'Some updated body';
        $this->updateAnnouncePostComment = $this->activityJsonBuilder->buildActivityJson($updateActivity);

        $this->testingApHttpClient->activityObjects[$this->updateAnnouncePostComment['id']] = $this->updateAnnouncePostComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemoteEntryInLocalMagazine(Entry $entry): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($entry, $this->remoteUser);
        $titleBefore = $entry->title;
        $entry->title = 'Some updated title';
        $entry->body = 'Some updated body';
        $this->updateCreateEntry = $this->RewriteTargetFieldsToLocal($entry->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        $entry->title = $titleBefore;

        $this->testingApHttpClient->activityObjects[$this->updateCreateEntry['id']] = $this->updateCreateEntry;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($comment, $this->remoteUser);
        $comment->body = 'Some updated body';
        $this->updateCreateEntryComment = $this->RewriteTargetFieldsToLocal($comment->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));

        $this->testingApHttpClient->activityObjects[$this->updateCreateEntryComment['id']] = $this->updateCreateEntryComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemotePostInLocalMagazine(Post $post): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($post, $this->remoteUser);
        $bodyBefore = $post->body;
        $post->body = 'Some updated body';
        $this->updateCreatePost = $this->RewriteTargetFieldsToLocal($post->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));
        $post->body = $bodyBefore;

        $this->testingApHttpClient->activityObjects[$this->updateCreatePost['id']] = $this->updateCreatePost;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateRemotePostCommentInLocalMagazine(PostComment $postComment): void
    {
        $updateActivity = $this->updateWrapper->buildForActivity($postComment, $this->remoteUser);
        $postComment->body = 'Some updated body';
        $this->updateCreatePostComment = $this->RewriteTargetFieldsToLocal($postComment->magazine, $this->activityJsonBuilder->buildActivityJson($updateActivity));

        $this->testingApHttpClient->activityObjects[$this->updateCreatePostComment['id']] = $this->updateCreatePostComment;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateUser(): void
    {
        $aboutBefore = $this->remoteUser->about;
        $this->remoteUser->about = 'Some updated user description';
        $this->remoteUser->publicKey = 'Some new public key';
        $this->remoteUser->privateKey = 'Some new private key';
        $updateActivity = $this->updateWrapper->buildForActor($this->remoteUser, $this->remoteUser);
        $this->updateUser = $this->activityJsonBuilder->buildActivityJson($updateActivity);
        $this->remoteUser->about = $aboutBefore;

        $this->testingApHttpClient->activityObjects[$this->updateUser['id']] = $this->updateUser;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }

    public function buildUpdateMagazine(): void
    {
        $descriptionBefore = $this->remoteMagazine->description;
        $this->remoteMagazine->description = 'Some updated magazine description';
        $this->remoteMagazine->publicKey = 'Some new public key';
        $this->remoteMagazine->privateKey = 'Some new private key';
        $updateActivity = $this->updateWrapper->buildForActor($this->remoteMagazine, $this->remoteMagazine->getOwner());
        $this->updateMagazine = $this->activityJsonBuilder->buildActivityJson($updateActivity);
        $this->remoteMagazine->description = $descriptionBefore;

        $this->testingApHttpClient->activityObjects[$this->updateMagazine['id']] = $this->updateMagazine;
        $this->entitiesToRemoveAfterSetup[] = $updateActivity;
    }
}

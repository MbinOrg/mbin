<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Entity\Magazine;
use App\Entity\User;
use App\Enums\EDirectMessageSettings;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class CreateHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $announceEntry;
    private array $announceEntryComment;
    private array $announcePost;
    private array $announcePostComment;
    private array $createEntry;
    private array $createEntryWithUrlAndImage;
    private array $createEntryComment;
    private array $createPost;
    private array $createPostComment;
    private array $createMessage;
    private array $createMastodonPostWithMention;
    private array $createMastodonPostWithMentionWithoutTagArray;

    public function setUpRemoteEntities(): void
    {
        $this->announceEntry = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser);
        $this->announceEntryComment = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser);
        $this->announcePost = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remoteUser);
        $this->announcePostComment = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remoteUser);
        $this->createEntry = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser);
        $this->createEntryWithUrlAndImage = $this->createRemoteEntryWithUrlAndImageInLocalMagazine($this->localMagazine, $this->remoteUser);
        $this->createEntryComment = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remoteUser);
        $this->createPost = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser);
        $this->createPostComment = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remoteUser);
        $this->createMessage = $this->createRemoteMessage($this->remoteUser, $this->localUser);
        $this->setupMastodonPost();
        $this->setupMastodonPostWithoutTagArray();
    }

    public function testCreateAnnouncedEntry(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->announceEntry['object']['object']['id']]);
        self::assertNotNull($entry);
    }

    #[Depends('testCreateAnnouncedEntry')]
    public function testCreateAnnouncedEntryComment(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntry)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announceEntryComment)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->announceEntryComment['object']['object']['id']]);
        self::assertNotNull($entryComment);
    }

    public function testCreateAnnouncedPost(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->announcePost['object']['object']['id']]);
        self::assertNotNull($post);
    }

    #[Depends('testCreateAnnouncedPost')]
    public function testCreateAnnouncedPostComment(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePost)));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->announcePostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->announcePostComment['object']['object']['id']]);
        self::assertNotNull($postComment);
    }

    public function testCreateEntry(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertNotNull($entry);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        // the id of the 'Create' activity should be wrapped in a 'Announce' activity
        self::assertEquals($this->createEntry['id'], $postedObjects[0]['payload']['object']['id']);
        self::assertEquals($this->createEntry['object']['id'], $postedObjects[0]['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedObjects[0]['inboxUrl']);
    }

    public function testCreateEntryWithUrlAndImage(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryWithUrlAndImage)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntryWithUrlAndImage['object']['id']]);
        self::assertNotNull($entry);
        self::assertNotNull($entry->image);
        self::assertNotNull($entry->url);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        // the id of the 'Create' activity should be wrapped in a 'Announce' activity
        self::assertEquals($this->createEntryWithUrlAndImage['id'], $postedObjects[0]['payload']['object']['id']);
        self::assertEquals($this->createEntryWithUrlAndImage['object']['id'], $postedObjects[0]['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedObjects[0]['inboxUrl']);
    }

    #[Depends('testCreateEntry')]
    public function testCreateEntryComment(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntry)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createEntry['object']['id']]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createEntryComment)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $this->createEntryComment['object']['id']]);
        self::assertNotNull($entryComment);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertCount(2, $postedObjects);
        // the id of the 'Create' activity should be wrapped in a 'Announce' activity
        self::assertEquals($this->createEntryComment['id'], $postedObjects[1]['payload']['object']['id']);
        self::assertEquals($this->createEntryComment['object']['id'], $postedObjects[1]['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedObjects[1]['inboxUrl']);
    }

    public function testCreatePost(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        // the id of the 'Create' activity should be wrapped in a 'Announce' activity
        self::assertEquals($this->createPost['id'], $postedObjects[0]['payload']['object']['id']);
        self::assertEquals($this->createPost['object']['id'], $postedObjects[0]['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedObjects[0]['inboxUrl']);
    }

    #[Depends('testCreatePost')]
    public function testCreatePostComment(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPost)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createPost['object']['id']]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createPostComment)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $this->createPostComment['object']['id']]);
        self::assertNotNull($postComment);
        self::assertTrue($this->localMagazine->isSubscribed($this->remoteSubscriber));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertCount(2, $postedObjects);
        // the id of the 'Create' activity should be wrapped in a 'Announce' activity
        self::assertEquals($this->createPostComment['id'], $postedObjects[1]['payload']['object']['id']);
        self::assertEquals($this->createPostComment['object']['id'], $postedObjects[1]['payload']['object']['object']['id']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedObjects[1]['inboxUrl']);
    }

    public function testCreateMessage(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMessage)));
        $message = $this->messageRepository->findOneBy(['apId' => $this->createMessage['object']['id']]);
        self::assertNotNull($message);
    }

    public function testCreateMessageFollowersOnlyFails(): void
    {
        $this->localUser->directMessageSetting = EDirectMessageSettings::FollowersOnly->value;
        self::expectException(HandlerFailedException::class);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMessage)));
    }

    public function testCreateMessageFollowersOnly(): void
    {
        $this->localUser->directMessageSetting = EDirectMessageSettings::FollowersOnly->value;
        $this->userManager->follow($this->remoteUser, $this->localUser);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMessage)));
        $message = $this->messageRepository->findOneBy(['apId' => $this->createMessage['object']['id']]);
        self::assertNotNull($message);
    }

    public function testCreateMessageNobodyFails(): void
    {
        $this->localUser->directMessageSetting = EDirectMessageSettings::Nobody->value;
        $this->userManager->follow($this->remoteUser, $this->localUser);
        self::expectException(HandlerFailedException::class);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMessage)));
    }

    public function testMastodonMentionInPost(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMastodonPostWithMention)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createMastodonPostWithMention['object']['id']]);
        self::assertNotNull($post);
        $mentions = $this->mentionManager->extract($post->body);
        self::assertCount(1, $mentions);
        self::assertEquals('@user@some.instance.tld', $mentions[0]);
    }

    public function testMastodonMentionInPostWithoutTagArray(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createMastodonPostWithMentionWithoutTagArray)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createMastodonPostWithMentionWithoutTagArray['object']['id']]);
        self::assertNotNull($post);
        $mentions = $this->mentionManager->extract($post->body);
        self::assertCount(1, $mentions);
        self::assertEquals('@remoteUser@remote.mbin', $mentions[0]);
    }

    private function setupMastodonPost(): void
    {
        $this->createMastodonPostWithMention = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser);
        unset($this->createMastodonPostWithMention['object']['source']);
        // this is what it would look like if a user created a post in Mastodon with just a single mention and nothing else
        $text = '<p><span class="h-card" translate="no"><a href="https://some.instance.tld/u/user" class="u-url mention">@<span>user</span></a></span>';
        $this->createMastodonPostWithMention['object']['contentMap']['en'] = $text;
        $this->createMastodonPostWithMention['object']['content'] = $text;
        $this->createMastodonPostWithMention['object']['tag'] = [
            [
                'type' => 'Mention',
                'href' => 'https://some.instance.tld/u/user',
                'name' => '@user@some.instance.tld',
            ],
        ];
    }

    private function setupMastodonPostWithoutTagArray(): void
    {
        $this->createMastodonPostWithMentionWithoutTagArray = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remoteUser);
        unset($this->createMastodonPostWithMentionWithoutTagArray['object']['source']);
        // this is what it would look like if a user created a post in Mastodon with just a single mention and nothing else
        $text = '<p><span class="h-card" translate="no"><a href="https://remote.mbin/u/remoteUser" class="u-url mention">@<span>remoteUser</span></a></span>';
        $this->createMastodonPostWithMentionWithoutTagArray['object']['contentMap']['en'] = $text;
        $this->createMastodonPostWithMentionWithoutTagArray['object']['content'] = $text;
    }

    private function createRemoteEntryWithUrlAndImageInLocalMagazine(Magazine $magazine, User $user): array
    {
        $entry = $this->getEntryByTitle('remote entry with URL and image in local', url: 'https://joinmbin.org', magazine: $magazine, user: $user, image: $this->getKibbyImageDto());
        $json = $this->pageFactory->create($entry, $this->tagLinkRepository->getTagsOfContent($entry));
        $this->testingApHttpClient->activityObjects[$json['id']] = $json;

        $createActivity = $this->createWrapper->build($entry);
        $create = $this->activityJsonBuilder->buildActivityJson($createActivity);
        $this->testingApHttpClient->activityObjects[$create['id']] = $create;

        $create = $this->RewriteTargetFieldsToLocal($magazine, $create);

        $this->entitiesToRemoveAfterSetup[] = $createActivity;
        $this->entitiesToRemoveAfterSetup[] = $entry;

        return $create;
    }
}

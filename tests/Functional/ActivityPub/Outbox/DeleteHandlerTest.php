<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Outbox;

use App\DTO\ModeratorDto;
use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class DeleteHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $createRemoteEntryInLocalMagazine;
    private array $createRemoteEntryInRemoteMagazine;
    private array $createRemoteEntryCommentInLocalMagazine;
    private array $createRemoteEntryCommentInRemoteMagazine;
    private array $createRemotePostInLocalMagazine;
    private array $createRemotePostInRemoteMagazine;
    private array $createRemotePostCommentInLocalMagazine;
    private array $createRemotePostCommentInRemoteMagazine;

    private User $remotePoster;

    public function setUp(): void
    {
        parent::setUp();
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->localUser));
    }

    public function setUpRemoteEntities(): void
    {
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemoteEntryCommentInRemoteMagazine = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemotePostInRemoteMagazine = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemotePostCommentInRemoteMagazine = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remotePoster);
        $this->createRemoteEntryCommentInLocalMagazine = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remotePoster);
        $this->createRemotePostInLocalMagazine = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remotePoster);
        $this->createRemotePostCommentInLocalMagazine = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remotePoster);
    }

    protected function setUpRemoteActors(): void
    {
        parent::setUpRemoteActors();
        $username = 'remotePoster';
        $domain = $this->remoteDomain;
        $this->remotePoster = $this->getUserByUsername($username, addImage: false);
        $this->registerActor($this->remotePoster, $domain, true);
    }

    public function testDeleteLocalEntryInLocalMagazineByLocalModerator(): void
    {
        $entry = $this->getEntryByTitle(title: 'test entry', magazine: $this->localMagazine, user: $this->localUser);
        $this->entryManager->delete($this->localUser, $entry);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteLocalEntryInRemoteMagazineByLocalModerator(): void
    {
        $entry = $this->getEntryByTitle(title: 'test entry', magazine: $this->remoteMagazine, user: $this->localUser);
        $this->entryManager->delete($this->localUser, $entry);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemoteEntryInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entryApId = $this->createRemoteEntryInLocalMagazine['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->entryManager->delete($this->localUser, $entry);
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertTrue($entry->isTrashed());
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemoteEntryInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entryApId = $this->createRemoteEntryInRemoteMagazine['object']['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->entryManager->purge($this->localUser, $entry);
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNull($entry);
        self::assertNotEmpty($this->testingApHttpClient->getPostedObjects());
        $deleteActivity = $this->activityRepository->findOneBy(['type' => 'Delete']);
        self::assertNotNull($deleteActivity);
        $activityId = $this->urlGenerator->generate('ap_object', ['id' => $deleteActivity->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->assertOneSentActivityOfType('Delete', $activityId);
    }

    public function testDeleteLocalEntryCommentInLocalMagazineByLocalModerator(): void
    {
        $entry = $this->getEntryByTitle(title: 'test entry', magazine: $this->localMagazine, user: $this->localUser);
        $comment = $this->createEntryComment('test entry comment', entry: $entry, user: $this->localUser);
        $this->removeActivitiesWithObject($comment);
        $this->entryCommentManager->delete($this->localUser, $comment);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteLocalEntryCommentInRemoteMagazineByLocalModerator(): void
    {
        $entry = $this->getEntryByTitle(title: 'test entry', magazine: $this->remoteMagazine, user: $this->localUser);
        $comment = $this->createEntryComment('test entry comment', entry: $entry, user: $this->localUser);
        $this->removeActivitiesWithObject($comment);
        $this->entryCommentManager->delete($this->localUser, $comment);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemoteEntryCommentInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entryApId = $this->createRemoteEntryInLocalMagazine['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryCommentInLocalMagazine)));
        $entryCommentApId = $this->createRemoteEntryCommentInLocalMagazine['object']['id'];
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertNotNull($entryComment);
        $this->entryCommentManager->delete($this->localUser, $entryComment);
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertTrue($entryComment->isTrashed());
        // 2 subs -> 2 delete activities
        $this->assertCountOfSentActivitiesOfType(2, 'Delete');
    }

    public function testDeleteRemoteEntryCommentInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entryApId = $this->createRemoteEntryInRemoteMagazine['object']['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryCommentInRemoteMagazine)));
        $entryCommentApId = $this->createRemoteEntryCommentInRemoteMagazine['object']['object']['id'];
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertNotNull($entryComment);
        $this->entryCommentManager->purge($this->localUser, $entryComment);
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertNull($entryComment);
        self::assertNotEmpty($this->testingApHttpClient->getPostedObjects());
        $deleteActivity = $this->activityRepository->findOneBy(['type' => 'Delete']);
        self::assertNotNull($deleteActivity);
        $activityId = $this->urlGenerator->generate('ap_object', ['id' => $deleteActivity->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->assertOneSentActivityOfType('Delete', $activityId);
    }

    public function testDeleteLocalPostInLocalMagazineByLocalModerator(): void
    {
        $post = $this->createPost(body: 'test post', magazine: $this->localMagazine, user: $this->localUser);
        $this->postManager->delete($this->localUser, $post);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteLocalPostInRemoteMagazineByLocalModerator(): void
    {
        $post = $this->createPost(body: 'test post', magazine: $this->remoteMagazine, user: $this->localUser);
        $this->postManager->delete($this->localUser, $post);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemotePostInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $postApId = $this->createRemotePostInLocalMagazine['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->postManager->delete($this->localUser, $post);
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertTrue($post->isTrashed());
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemotePostInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $postApId = $this->createRemotePostInRemoteMagazine['object']['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->postManager->purge($this->localUser, $post);
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNull($post);
        self::assertNotEmpty($this->testingApHttpClient->getPostedObjects());
        $deleteActivity = $this->activityRepository->findOneBy(['type' => 'Delete']);
        self::assertNotNull($deleteActivity);
        $activityId = $this->urlGenerator->generate('ap_object', ['id' => $deleteActivity->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->assertOneSentActivityOfType('Delete', $activityId);
    }

    public function testDeleteLocalPostCommentInLocalMagazineByLocalModerator(): void
    {
        $post = $this->createPost(body: 'test post', magazine: $this->localMagazine, user: $this->localUser);
        $comment = $this->createPostComment('test post comment', post: $post, user: $this->localUser);
        $this->removeActivitiesWithObject($comment);
        $this->postCommentManager->delete($this->localUser, $comment);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteLocalPostCommentInRemoteMagazineByLocalModerator(): void
    {
        $post = $this->createPost(body: 'test post', magazine: $this->remoteMagazine, user: $this->localUser);
        $comment = $this->createPostComment('test post comment', post: $post, user: $this->localUser);
        $this->removeActivitiesWithObject($comment);
        $this->postCommentManager->delete($this->localUser, $comment);
        $this->assertOneSentActivityOfType('Delete');
    }

    public function testDeleteRemotePostCommentInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $postApId = $this->createRemotePostInLocalMagazine['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostCommentInLocalMagazine)));
        $postCommentApId = $this->createRemotePostCommentInLocalMagazine['object']['id'];
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertNotNull($postComment);
        $this->postCommentManager->delete($this->localUser, $postComment);
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertTrue($postComment->isTrashed());
        // 2 subs -> 2 delete activities
        $this->assertCountOfSentActivitiesOfType(2, 'Delete');
    }

    public function testDeleteRemotePostCommentInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $postApId = $this->createRemotePostInRemoteMagazine['object']['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostCommentInRemoteMagazine)));
        $postCommentApId = $this->createRemotePostCommentInRemoteMagazine['object']['object']['id'];
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertNotNull($postComment);
        $this->postCommentManager->purge($this->localUser, $postComment);
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertNull($postComment);
        self::assertNotEmpty($this->testingApHttpClient->getPostedObjects());
        $deleteActivity = $this->activityRepository->findOneBy(['type' => 'Delete']);
        self::assertNotNull($deleteActivity);
        $activityId = $this->urlGenerator->generate('ap_object', ['id' => $deleteActivity->uuid], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->assertOneSentActivityOfType('Delete', $activityId);
    }

    public function removeActivitiesWithObject(ActivityPubActivityInterface|ActivityPubActorInterface $object): void
    {
        $activities = $this->activityRepository->findAllActivitiesByObject($object);
        foreach ($activities as $activity) {
            $this->entityManager->remove($activity);
        }
    }
}

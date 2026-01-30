<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

use function PHPUnit\Framework\assertNotNull;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class DeleteHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $createRemoteEntryInLocalMagazine;
    private array $deleteRemoteEntryByRemoteModeratorInLocalMagazine;
    private array $createRemoteEntryInRemoteMagazine;
    private array $deleteRemoteEntryByRemoteModeratorInRemoteMagazine;
    private array $createRemoteEntryCommentInLocalMagazine;
    private array $deleteRemoteEntryCommentByRemoteModeratorInLocalMagazine;
    private array $createRemoteEntryCommentInRemoteMagazine;
    private array $deleteRemoteEntryCommentByRemoteModeratorInRemoteMagazine;
    private array $createRemotePostInLocalMagazine;
    private array $deleteRemotePostByRemoteModeratorInLocalMagazine;
    private array $createRemotePostInRemoteMagazine;
    private array $deleteRemotePostByRemoteModeratorInRemoteMagazine;
    private array $createRemotePostCommentInLocalMagazine;
    private array $deleteRemotePostCommentByRemoteModeratorInLocalMagazine;
    private array $createRemotePostCommentInRemoteMagazine;
    private array $deleteRemotePostCommentByRemoteModeratorInRemoteMagazine;

    private User $remotePoster;

    public function testDeleteLocalEntryInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryAndCreateDeleteActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $entry = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteLocalEntryInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryAndCreateDeleteActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $entry = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteRemoteEntryInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entryApId = $this->createRemoteEntryInLocalMagazine['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemoteEntryByRemoteModeratorInLocalMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertTrue($entry->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $this->deleteRemoteEntryByRemoteModeratorInLocalMagazine['id']);
    }

    public function testDeleteRemoteEntryInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entryApId = $this->createRemoteEntryInRemoteMagazine['object']['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemoteEntryByRemoteModeratorInRemoteMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertTrue($entry->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $deleteActivities = $this->activityRepository->findBy(['type' => 'Delete']);
        self::assertEmpty($deleteActivities);
    }

    public function testDeleteLocalEntryCommentInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryCommentAndCreateDeleteActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $entryComment = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entryComment);
        self::assertTrue($entryComment->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteLocalEntryCommentInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryCommentAndCreateDeleteActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $entryComment = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entryComment);
        self::assertTrue($entryComment->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteRemoteEntryCommentInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entryApId = $this->createRemoteEntryInLocalMagazine['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryCommentInLocalMagazine)));
        $entryCommentApId = $this->createRemoteEntryCommentInLocalMagazine['object']['id'];
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertNotNull($entryComment);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemoteEntryCommentByRemoteModeratorInLocalMagazine)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertTrue($entryComment->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $this->deleteRemoteEntryCommentByRemoteModeratorInLocalMagazine['id']);
    }

    public function testDeleteRemoteEntryCommentInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entryApId = $this->createRemoteEntryInRemoteMagazine['object']['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryCommentInRemoteMagazine)));
        $entryCommentApId = $this->createRemoteEntryCommentInRemoteMagazine['object']['object']['id'];
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertNotNull($entryComment);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemoteEntryCommentByRemoteModeratorInRemoteMagazine)));
        $entryComment = $this->entryCommentRepository->findOneBy(['apId' => $entryCommentApId]);
        self::assertTrue($entryComment->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $deleteActivities = $this->activityRepository->findBy(['type' => 'Delete']);
        self::assertEmpty($deleteActivities);
    }

    public function testDeleteLocalPostInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostAndCreateDeleteActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $post = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($post);
        self::assertTrue($post->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteLocalPostInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostAndCreateDeleteActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $post = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($post);
        self::assertTrue($post->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteRemotePostInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $postApId = $this->createRemotePostInLocalMagazine['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemotePostByRemoteModeratorInLocalMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertTrue($post->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $this->deleteRemotePostByRemoteModeratorInLocalMagazine['id']);
    }

    public function testDeleteRemotePostInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $postApId = $this->createRemotePostInRemoteMagazine['object']['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemotePostByRemoteModeratorInRemoteMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertTrue($post->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $deleteActivities = $this->activityRepository->findBy(['type' => 'Delete']);
        self::assertEmpty($deleteActivities);
    }

    public function testDeleteLocalPostCommentInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostCommentAndCreateDeleteActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $PostComment = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($PostComment);
        self::assertTrue($PostComment->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteLocalPostCommentInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostCommentAndCreateDeleteActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $postComment = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($postComment);
        self::assertTrue($postComment->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertOneSentAnnouncedActivityOfType('Delete', $activity['id']);
    }

    public function testDeleteRemotePostCommentInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $postApId = $this->createRemotePostInLocalMagazine['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostCommentInLocalMagazine)));
        $postCommentApId = $this->createRemotePostCommentInLocalMagazine['object']['id'];
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertNotNull($postComment);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemotePostCommentByRemoteModeratorInLocalMagazine)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertTrue($postComment->isTrashed());
        $this->assertOneSentAnnouncedActivityOfType('Delete', $this->deleteRemotePostCommentByRemoteModeratorInLocalMagazine['id']);
    }

    public function testDeleteRemotePostCommentInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $postApId = $this->createRemotePostInRemoteMagazine['object']['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostCommentInRemoteMagazine)));
        $postCommentApId = $this->createRemotePostCommentInRemoteMagazine['object']['object']['id'];
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertNotNull($postComment);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->deleteRemotePostCommentByRemoteModeratorInRemoteMagazine)));
        $postComment = $this->postCommentRepository->findOneBy(['apId' => $postCommentApId]);
        self::assertTrue($postComment->isTrashed());
        $this->assertCountOfSentActivitiesOfType(0, 'Delete');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $deleteActivities = $this->activityRepository->findBy(['type' => 'Delete']);
        self::assertEmpty($deleteActivities);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->remoteUser));
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->localUser));
        $this->magazineManager->addModerator(new ModeratorDto($this->localMagazine, $this->remoteUser, $this->localMagazine->getOwner()));
        $this->magazineManager->subscribe($this->remoteMagazine, $this->remoteSubscriber);
    }

    protected function setUpRemoteActors(): void
    {
        parent::setUpRemoteActors();
        $username = 'remotePoster';
        $domain = $this->remoteDomain;
        $this->remotePoster = $this->getUserByUsername($username, addImage: false);
        $this->registerActor($this->remotePoster, $domain, true);
    }

    public function setUpRemoteEntities(): void
    {
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($entry) => $this->createDeletesFromRemoteEntryInRemoteMagazine($entry));
        $this->createRemoteEntryCommentInRemoteMagazine = $this->createRemoteEntryCommentInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($entryComment) => $this->createDeletesFromRemoteEntryCommentInRemoteMagazine($entryComment));
        $this->createRemotePostInRemoteMagazine = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($post) => $this->createDeletesFromRemotePostInRemoteMagazine($post));
        $this->createRemotePostCommentInRemoteMagazine = $this->createRemotePostCommentInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($comment) => $this->createDeletesFromRemotePostCommentInRemoteMagazine($comment));
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($entry) => $this->createDeletesFromRemoteEntryInLocalMagazine($entry));
        $this->createRemoteEntryCommentInLocalMagazine = $this->createRemoteEntryCommentInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($entryComment) => $this->createDeletesFromRemoteEntryCommentInLocalMagazine($entryComment));
        $this->createRemotePostInLocalMagazine = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($post) => $this->createDeletesFromRemotePostInLocalMagazine($post));
        $this->createRemotePostCommentInLocalMagazine = $this->createRemotePostCommentInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($comment) => $this->createDeletesFromRemotePostCommentInLocalMagazine($comment));
    }

    private function createDeletesFromRemoteEntryInRemoteMagazine(Entry $createdEntry): void
    {
        $this->deleteRemoteEntryByRemoteModeratorInRemoteMagazine = $this->createDeleteForContent($createdEntry);
    }

    private function createDeletesFromRemoteEntryInLocalMagazine(Entry $createdEntry): void
    {
        $this->deleteRemoteEntryByRemoteModeratorInLocalMagazine = $this->createDeleteForContent($createdEntry);
    }

    private function createDeletesFromRemoteEntryCommentInRemoteMagazine(EntryComment $comment): void
    {
        $this->deleteRemoteEntryCommentByRemoteModeratorInRemoteMagazine = $this->createDeleteForContent($comment);
    }

    private function createDeletesFromRemoteEntryCommentInLocalMagazine(EntryComment $comment): void
    {
        $this->deleteRemoteEntryCommentByRemoteModeratorInLocalMagazine = $this->createDeleteForContent($comment);
    }

    private function createDeletesFromRemotePostInRemoteMagazine(Post $post): void
    {
        $this->deleteRemotePostByRemoteModeratorInRemoteMagazine = $this->createDeleteForContent($post);
    }

    private function createDeletesFromRemotePostInLocalMagazine(Post $ost): void
    {
        $this->deleteRemotePostByRemoteModeratorInLocalMagazine = $this->createDeleteForContent($ost);
    }

    private function createDeletesFromRemotePostCommentInRemoteMagazine(PostComment $comment): void
    {
        $this->deleteRemotePostCommentByRemoteModeratorInRemoteMagazine = $this->createDeleteForContent($comment);
    }

    private function createDeletesFromRemotePostCommentInLocalMagazine(PostComment $comment): void
    {
        $this->deleteRemotePostCommentByRemoteModeratorInLocalMagazine = $this->createDeleteForContent($comment);
    }

    private function createDeleteForContent(Entry|EntryComment|Post|PostComment $content): array
    {
        $activity = $this->deleteWrapper->build($content, $this->remoteUser);
        $json = $this->activityJsonBuilder->buildActivityJson($activity);
        $json['summary'] = ' ';

        $this->testingApHttpClient->activityObjects[$json['id']] = $json;
        $this->entitiesToRemoveAfterSetup[] = $activity;

        return $json;
    }

    /**
     * @return array{entry:Entry, activity: array}
     */
    private function createLocalEntryAndCreateDeleteActivity(Magazine $magazine, User $author, User $deletingUser): array
    {
        $entry = $this->getEntryByTitle('localEntry', magazine: $magazine, user: $author);
        $entryJson = $this->pageFactory->create($entry, [], false);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->deleteWrapper->build($entry, $deletingUser);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $entryJson;
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);

        return [
            'activity' => $activityJson,
            'content' => $entry,
        ];
    }

    /**
     * @return array{content:EntryComment, activity: array}
     */
    private function createLocalEntryCommentAndCreateDeleteActivity(Magazine $magazine, User $author, User $deletingUser): array
    {
        $parent = $this->getEntryByTitle('localEntry', magazine: $magazine, user: $author);
        $comment = $this->createEntryComment('localEntryComment', entry: $parent, user: $author);
        $commentJson = $this->entryCommentNoteFactory->create($comment, []);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->deleteWrapper->build($comment, $deletingUser);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $commentJson;
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);

        return [
            'activity' => $activityJson,
            'content' => $comment,
        ];
    }

    /**
     * @return array{content:EntryComment, activity: array}
     */
    private function createLocalPostAndCreateDeleteActivity(Magazine $magazine, User $author, User $deletingUser): array
    {
        $post = $this->createPost('localPost', magazine: $magazine, user: $author);
        $postJson = $this->postNoteFactory->create($post, []);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->deleteWrapper->build($post, $deletingUser);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $postJson;
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);

        return [
            'activity' => $activityJson,
            'content' => $post,
        ];
    }

    /**
     * @return array{content:EntryComment, activity: array}
     */
    private function createLocalPostCommentAndCreateDeleteActivity(Magazine $magazine, User $author, User $deletingUser): array
    {
        $parent = $this->createPost('localPost', magazine: $magazine, user: $author);
        $postComment = $this->createPostComment('localPost', post: $parent, user: $author);
        $commentJson = $this->postCommentNoteFactory->create($postComment, []);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->deleteWrapper->build($postComment, $deletingUser);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $commentJson;
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);

        return [
            'activity' => $activityJson,
            'content' => $postComment,
        ];
    }
}

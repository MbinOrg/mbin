<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class LockHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $createRemoteEntryInLocalMagazine;
    private array $lockRemoteEntryByRemoteModeratorInLocalMagazine;
    private array $undoLockRemoteEntryByRemoteModeratorInLocalMagazine;
    private array $createRemoteEntryInRemoteMagazine;
    private array $lockRemoteEntryByRemoteModeratorInRemoteMagazine;
    private array $undoLockRemoteEntryByRemoteModeratorInRemoteMagazine;
    private array $createRemotePostInLocalMagazine;
    private array $lockRemotePostByRemoteModeratorInLocalMagazine;
    private array $undoLockRemotePostByRemoteModeratorInLocalMagazine;
    private array $createRemotePostInRemoteMagazine;
    private array $lockRemotePostByRemoteModeratorInRemoteMagazine;
    private array $undoLockRemotePostByRemoteModeratorInRemoteMagazine;

    private User $remotePoster;

    public function testLockLocalEntryInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryAndCreateLockActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        /** @var Entry $entry */
        $entry = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Lock', $activity['id']);

        $this->bus->dispatch(new ActivityMessage(json_encode($obj['undo'])));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Undo', $obj['undo']['id']);
    }

    public function testLockLocalEntryInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalEntryAndCreateLockActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        /** @var Entry $entry */
        $entry = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Lock');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');

        $this->bus->dispatch(new ActivityMessage(json_encode($obj['undo'])));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Undo');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
    }

    public function testLockRemoteEntryInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entryApId = $this->createRemoteEntryInLocalMagazine['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->lockRemoteEntryByRemoteModeratorInLocalMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertTrue($entry->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Lock', $this->lockRemoteEntryByRemoteModeratorInLocalMagazine['id']);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLockRemoteEntryByRemoteModeratorInLocalMagazine)));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Undo', $this->undoLockRemoteEntryByRemoteModeratorInLocalMagazine['id']);
    }

    public function testLockRemoteEntryInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entryApId = $this->createRemoteEntryInRemoteMagazine['object']['object']['id'];
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertNotNull($entry);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->lockRemoteEntryByRemoteModeratorInRemoteMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $entryApId]);
        self::assertTrue($entry->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Lock');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $lockActivities = $this->activityRepository->findBy(['type' => 'Lock']);
        self::assertEmpty($lockActivities);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLockRemoteEntryByRemoteModeratorInRemoteMagazine)));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Undo');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
    }

    public function testLockLocalPostInLocalMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostAndCreateLockActivity($this->localMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $post = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($post);
        self::assertTrue($post->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Lock', $activity['id']);

        $this->bus->dispatch(new ActivityMessage(json_encode($obj['undo'])));
        $this->entityManager->refresh($post);
        self::assertFalse($post->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Undo', $obj['undo']['id']);
    }

    public function testLockLocalPostInRemoteMagazineByRemoteModerator(): void
    {
        $obj = $this->createLocalPostAndCreateLockActivity($this->remoteMagazine, $this->localUser, $this->remoteUser);
        $activity = $obj['activity'];
        $post = $obj['content'];
        $this->bus->dispatch(new ActivityMessage(json_encode($activity)));
        $this->entityManager->refresh($post);
        self::assertTrue($post->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Lock');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');

        $this->bus->dispatch(new ActivityMessage(json_encode($obj['undo'])));
        $this->entityManager->refresh($post);
        self::assertFalse($post->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Undo');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
    }

    public function testLockRemotePostInLocalMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $postApId = $this->createRemotePostInLocalMagazine['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->lockRemotePostByRemoteModeratorInLocalMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertTrue($post->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Lock', $this->lockRemotePostByRemoteModeratorInLocalMagazine['id']);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLockRemotePostByRemoteModeratorInLocalMagazine)));
        $this->entityManager->refresh($post);
        self::assertFalse($post->isLocked);
        $this->assertOneSentAnnouncedActivityOfType('Undo', $this->undoLockRemotePostByRemoteModeratorInLocalMagazine['id']);
    }

    public function testLockRemotePostInRemoteMagazineByRemoteModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $postApId = $this->createRemotePostInRemoteMagazine['object']['object']['id'];
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertNotNull($post);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->lockRemotePostByRemoteModeratorInRemoteMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $postApId]);
        self::assertTrue($post->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Lock');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
        $lockActivities = $this->activityRepository->findBy(['type' => 'Lock']);
        self::assertEmpty($lockActivities);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoLockRemotePostByRemoteModeratorInRemoteMagazine)));
        $this->entityManager->refresh($post);
        self::assertFalse($post->isLocked);
        $this->assertCountOfSentActivitiesOfType(0, 'Undo');
        $this->assertCountOfSentActivitiesOfType(0, 'Announce');
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
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($entry) => $this->createLockFromRemoteEntryInRemoteMagazine($entry));
        $this->createRemotePostInRemoteMagazine = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remotePoster, fn ($post) => $this->createLockFromRemotePostInRemoteMagazine($post));
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($entry) => $this->createLockFromRemoteEntryInLocalMagazine($entry));
        $this->createRemotePostInLocalMagazine = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remotePoster, fn ($post) => $this->createLockFromRemotePostInLocalMagazine($post));
    }

    private function createLockFromRemoteEntryInRemoteMagazine(Entry $createdEntry): void
    {
        $activities = $this->createLockAndUnlockForContent($createdEntry);
        $this->lockRemoteEntryByRemoteModeratorInRemoteMagazine = $activities['lock'];
        $this->undoLockRemoteEntryByRemoteModeratorInRemoteMagazine = $activities['unlock'];
    }

    private function createLockFromRemoteEntryInLocalMagazine(Entry $createdEntry): void
    {
        $activities = $this->createLockAndUnlockForContent($createdEntry);
        $this->lockRemoteEntryByRemoteModeratorInLocalMagazine = $activities['lock'];
        $this->undoLockRemoteEntryByRemoteModeratorInLocalMagazine = $activities['unlock'];
    }

    private function createLockFromRemotePostInRemoteMagazine(Post $post): void
    {
        $activities = $this->createLockAndUnlockForContent($post);
        $this->lockRemotePostByRemoteModeratorInRemoteMagazine = $activities['lock'];
        $this->undoLockRemotePostByRemoteModeratorInRemoteMagazine = $activities['unlock'];
    }

    private function createLockFromRemotePostInLocalMagazine(Post $ost): void
    {
        $activities = $this->createLockAndUnlockForContent($ost);
        $this->lockRemotePostByRemoteModeratorInLocalMagazine = $activities['lock'];
        $this->undoLockRemotePostByRemoteModeratorInLocalMagazine = $activities['unlock'];
    }

    /**
     * @return array{lock: array, unlock: array}
     */
    private function createLockAndUnlockForContent(Entry|Post $content): array
    {
        $activity = $this->lockFactory->build($this->remoteUser, $content);
        $lock = $this->activityJsonBuilder->buildActivityJson($activity);

        $undoActivity = $this->undoWrapper->build($activity);
        $unlock = $this->activityJsonBuilder->buildActivityJson($undoActivity);

        $this->testingApHttpClient->activityObjects[$lock['id']] = $lock;
        $this->testingApHttpClient->activityObjects[$unlock['id']] = $unlock;
        $this->entitiesToRemoveAfterSetup[] = $activity;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;

        return [
            'lock' => $lock,
            'unlock' => $unlock,
        ];
    }

    /**
     * @return array{entry: Entry, activity: array, undo: array}
     */
    private function createLocalEntryAndCreateLockActivity(Magazine $magazine, User $author, User $lockingUser): array
    {
        $entry = $this->getEntryByTitle('localEntry', magazine: $magazine, user: $author);
        $entryJson = $this->pageFactory->create($entry, [], false);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->lockFactory->build($lockingUser, $entry);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $entryJson['id'];
        $undoActivity = $this->undoWrapper->build($activity);
        $undoJson = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $undoJson['object']['object'] = $entryJson['id'];
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);
        $this->entityManager->remove($undoActivity);

        return [
            'activity' => $activityJson,
            'content' => $entry,
            'undo' => $undoJson,
        ];
    }

    /**
     * @return array{content:Post, activity: array, undo: array}
     */
    private function createLocalPostAndCreateLockActivity(Magazine $magazine, User $author, User $lockingUser): array
    {
        $post = $this->createPost('localPost', magazine: $magazine, user: $author);
        $postJson = $this->postNoteFactory->create($post, []);
        $this->switchToRemoteDomain($this->remoteDomain);
        $activity = $this->lockFactory->build($lockingUser, $post);
        $activityJson = $this->activityJsonBuilder->buildActivityJson($activity);
        $activityJson['object'] = $postJson['id'];
        $undoActivity = $this->undoWrapper->build($activity);
        $undoJson = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $undoJson['object']['object'] = $postJson['id'];
        $this->switchToLocalDomain();

        $this->entityManager->remove($activity);
        $this->entityManager->remove($undoActivity);

        return [
            'activity' => $activityJson,
            'content' => $post,
            'undo' => $undoJson,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\DTO\ModeratorDto;
use App\Entity\Entry;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class AddHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $addModeratorRemoteMagazine;

    private array $addModeratorLocalMagazine;

    private array $createRemoteEntryInRemoteMagazine;

    private array $addPinnedEntryRemoteMagazine;

    private array $createRemoteEntryInLocalMagazine;

    private array $addPinnedEntryLocalMagazine;

    public function setUp(): void
    {
        parent::setUp();
        // it is important that the moderators are initialized here, as they would be removed from the db if added in `setupRemoteEntries`
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->remoteUser, $this->remoteMagazine->getOwner()));
        $this->magazineManager->addModerator(new ModeratorDto($this->localMagazine, $this->remoteUser, $this->localUser));
    }

    public function testAddModeratorInRemoteMagazine(): void
    {
        self::assertFalse($this->remoteMagazine->userIsModerator($this->remoteSubscriber));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->addModeratorRemoteMagazine)));
        self::assertTrue($this->remoteMagazine->userIsModerator($this->remoteSubscriber));
    }

    public function testAddModeratorLocalMagazine(): void
    {
        self::assertFalse($this->localMagazine->userIsModerator($this->remoteSubscriber));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->addModeratorLocalMagazine)));
        self::assertTrue($this->localMagazine->userIsModerator($this->remoteSubscriber));

        $this->assertAddSentToSubscriber($this->addModeratorLocalMagazine);
    }

    public function testAddPinnedEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInRemoteMagazine['object']['object']['id']]);
        self::assertNotNull($entry);
        self::assertFalse($entry->sticky);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->addPinnedEntryRemoteMagazine)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->sticky);
    }

    public function testAddPinnedEntryLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInLocalMagazine['object']['id']]);
        self::assertNotNull($entry);
        self::assertFalse($entry->sticky);

        $this->bus->dispatch(new ActivityMessage(json_encode($this->addPinnedEntryLocalMagazine)));
        $this->entityManager->refresh($entry);
        self::assertTrue($entry->sticky);
        $this->assertAddSentToSubscriber($this->addPinnedEntryLocalMagazine);
    }

    public function setUpRemoteEntities(): void
    {
        $this->buildAddModeratorInRemoteMagazine();
        $this->buildAddModeratorInLocalMagazine();
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildAddPinnedPostInRemoteMagazine($entry));
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildAddPinnedPostInLocalMagazine($entry));
    }

    private function buildAddModeratorInRemoteMagazine(): void
    {
        $addActivity = $this->addRemoveFactory->buildAddModerator($this->remoteUser, $this->remoteSubscriber, $this->remoteMagazine);
        $this->addModeratorRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($addActivity);
        $this->addModeratorRemoteMagazine['object'] = 'https://remote.sub.mbin/u/remoteSubscriber';

        $this->testingApHttpClient->activityObjects[$this->addModeratorRemoteMagazine['id']] = $this->addModeratorRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $addActivity;
    }

    private function buildAddModeratorInLocalMagazine(): void
    {
        $addActivity = $this->addRemoveFactory->buildAddModerator($this->remoteUser, $this->remoteSubscriber, $this->localMagazine);
        $this->addModeratorLocalMagazine = $this->activityJsonBuilder->buildActivityJson($addActivity);
        $this->addModeratorLocalMagazine['target'] = 'https://kbin.test/m/magazine/moderators';
        $this->addModeratorLocalMagazine['object'] = 'https://remote.sub.mbin/u/remoteSubscriber';

        $this->testingApHttpClient->activityObjects[$this->addModeratorLocalMagazine['id']] = $this->addModeratorLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $addActivity;
    }

    private function buildAddPinnedPostInRemoteMagazine(Entry $entry): void
    {
        $addActivity = $this->addRemoveFactory->buildAddPinnedPost($this->remoteUser, $entry);
        $this->addPinnedEntryRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($addActivity);

        $this->testingApHttpClient->activityObjects[$this->addPinnedEntryRemoteMagazine['id']] = $this->addPinnedEntryRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $addActivity;
    }

    private function buildAddPinnedPostInLocalMagazine(Entry $entry): void
    {
        $addActivity = $this->addRemoveFactory->buildAddPinnedPost($this->remoteUser, $entry);
        $this->addPinnedEntryLocalMagazine = $this->activityJsonBuilder->buildActivityJson($addActivity);
        $this->addPinnedEntryLocalMagazine['target'] = 'https://kbin.test/m/magazine/pinned';

        $this->testingApHttpClient->activityObjects[$this->addPinnedEntryLocalMagazine['id']] = $this->addPinnedEntryLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $addActivity;
    }

    private function assertAddSentToSubscriber(array $originalPayload): void
    {
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedAddAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Add' === $arr['payload']['object']['type']);
        $postedAddAnnounce = $postedAddAnnounces[array_key_first($postedAddAnnounces)];
        // the id of the 'Add' activity should be wrapped in an 'Announce' activity
        self::assertEquals($originalPayload['id'], $postedAddAnnounce['payload']['object']['id']);
        self::assertEquals($originalPayload['object'], $postedAddAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedAddAnnounce['inboxUrl']);
    }
}

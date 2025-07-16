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
class RemoveHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $removeModeratorRemoteMagazine;

    private array $removeModeratorLocalMagazine;

    private array $createRemoteEntryInRemoteMagazine;

    private array $removePinnedEntryRemoteMagazine;

    private array $createRemoteEntryInLocalMagazine;

    private array $removePinnedEntryLocalMagazine;

    public function setUp(): void
    {
        parent::setUp();
        $this->remoteMagazine = $this->activityPubManager->findActorOrCreate('!remoteMagazine@remote.mbin');
        $this->remoteUser = $this->activityPubManager->findActorOrCreate('@remoteUser@remote.mbin');
        // it is important that the moderators are initialized here, as they would be removed from the db if added in `setupRemoteEntries`
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->remoteUser, $this->remoteMagazine->getOwner()));
        $this->magazineManager->addModerator(new ModeratorDto($this->localMagazine, $this->remoteUser, $this->localUser));
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->remoteSubscriber, $this->remoteMagazine->getOwner()));
        $this->magazineManager->addModerator(new ModeratorDto($this->localMagazine, $this->remoteSubscriber, $this->localUser));
    }

    public function testRemoveModeratorInRemoteMagazine(): void
    {
        self::assertTrue($this->remoteMagazine->userIsModerator($this->remoteSubscriber));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->removeModeratorRemoteMagazine)));
        self::assertFalse($this->remoteMagazine->userIsModerator($this->remoteSubscriber));
    }

    public function testRemoveModeratorLocalMagazine(): void
    {
        self::assertTrue($this->localMagazine->userIsModerator($this->remoteSubscriber));
        $this->bus->dispatch(new ActivityMessage(json_encode($this->removeModeratorLocalMagazine)));
        self::assertFalse($this->localMagazine->userIsModerator($this->remoteSubscriber));

        $this->assertRemoveSentToSubscriber($this->removeModeratorLocalMagazine);
    }

    public function testRemovePinnedEntryInRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInRemoteMagazine['object']['object']['id']]);
        self::assertNotNull($entry);
        $entry->sticky = true;
        $this->entityManager->flush();

        $this->bus->dispatch(new ActivityMessage(json_encode($this->removePinnedEntryRemoteMagazine)));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->sticky);
    }

    public function testRemovePinnedEntryLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInLocalMagazine['object']['id']]);
        self::assertNotNull($entry);
        $entry->sticky = true;
        $this->entityManager->flush();

        $this->bus->dispatch(new ActivityMessage(json_encode($this->removePinnedEntryLocalMagazine)));
        $this->entityManager->refresh($entry);
        self::assertFalse($entry->sticky);
        $this->assertRemoveSentToSubscriber($this->removePinnedEntryLocalMagazine);
    }

    public function setUpRemoteEntities(): void
    {
        $this->buildRemoveModeratorInRemoteMagazine();
        $this->buildRemoveModeratorInLocalMagazine();
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildRemovePinnedPostInRemoteMagazine($entry));
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remoteUser, fn (Entry $entry) => $this->buildRemovePinnedPostInLocalMagazine($entry));
    }

    private function buildRemoveModeratorInRemoteMagazine(): void
    {
        $removeActivity = $this->addRemoveFactory->buildRemoveModerator($this->remoteUser, $this->remoteSubscriber, $this->remoteMagazine);
        $this->removeModeratorRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($removeActivity);
        $this->removeModeratorRemoteMagazine['object'] = 'https://remote.sub.mbin/u/remoteSubscriber';

        $this->testingApHttpClient->activityObjects[$this->removeModeratorRemoteMagazine['id']] = $this->removeModeratorRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $removeActivity;
    }

    private function buildRemoveModeratorInLocalMagazine(): void
    {
        $removeActivity = $this->addRemoveFactory->buildRemoveModerator($this->remoteUser, $this->remoteSubscriber, $this->localMagazine);
        $this->removeModeratorLocalMagazine = $this->activityJsonBuilder->buildActivityJson($removeActivity);
        $this->removeModeratorLocalMagazine['target'] = 'https://kbin.test/m/magazine/moderators';
        $this->removeModeratorLocalMagazine['object'] = 'https://remote.sub.mbin/u/remoteSubscriber';

        $this->testingApHttpClient->activityObjects[$this->removeModeratorLocalMagazine['id']] = $this->removeModeratorLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $removeActivity;
    }

    private function buildRemovePinnedPostInRemoteMagazine(Entry $entry): void
    {
        $removeActivity = $this->addRemoveFactory->buildRemovePinnedPost($this->remoteUser, $entry);
        $this->removePinnedEntryRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($removeActivity);

        $this->testingApHttpClient->activityObjects[$this->removePinnedEntryRemoteMagazine['id']] = $this->removePinnedEntryRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $removeActivity;
    }

    private function buildRemovePinnedPostInLocalMagazine(Entry $entry): void
    {
        $removeActivity = $this->addRemoveFactory->buildRemovePinnedPost($this->remoteUser, $entry);
        $this->removePinnedEntryLocalMagazine = $this->activityJsonBuilder->buildActivityJson($removeActivity);
        $this->removePinnedEntryLocalMagazine['target'] = 'https://kbin.test/m/magazine/pinned';

        $this->testingApHttpClient->activityObjects[$this->removePinnedEntryLocalMagazine['id']] = $this->removePinnedEntryLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $removeActivity;
    }

    private function assertRemoveSentToSubscriber(array $originalPayload): void
    {
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertNotEmpty($postedObjects);
        $postedAddAnnounces = array_filter($postedObjects, fn ($arr) => 'Announce' === $arr['payload']['type'] && 'Remove' === $arr['payload']['object']['type']);
        $postedAddAnnounce = $postedAddAnnounces[array_key_first($postedAddAnnounces)];
        // the id of the 'Remove' activity should be wrapped in an 'Announce' activity
        self::assertEquals($originalPayload['id'], $postedAddAnnounce['payload']['object']['id']);
        self::assertEquals($originalPayload['object'], $postedAddAnnounce['payload']['object']['object']);
        self::assertEquals($this->remoteSubscriber->apInboxUrl, $postedAddAnnounce['inboxUrl']);
    }
}

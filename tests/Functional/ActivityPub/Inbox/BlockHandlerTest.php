<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\DTO\MagazineBanDto;
use App\DTO\ModeratorDto;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class BlockHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $blockLocalUserLocalMagazine;
    private array $undoBlockLocalUserLocalMagazine;
    private array $blockRemoteUserLocalMagazine;
    private array $undoBlockRemoteUserLocalMagazine;
    private array $blockLocalUserRemoteMagazine;
    private array $undoBlockLocalUserRemoteMagazine;
    private array $blockRemoteUserRemoteMagazine;
    private array $undoBlockRemoteUserRemoteMagazine;
    private array $instanceBanRemoteUser;
    private array $undoInstanceBanRemoteUser;

    private User $localSubscriber;
    private User $remoteAdmin;

    public function setUp(): void
    {
        parent::setUp();
        // it is important that the moderators are initialized here, as they would be removed from the db if added in `setupRemoteEntries`
        $this->magazineManager->addModerator(new ModeratorDto($this->localMagazine, $this->remoteSubscriber, $this->localUser));
        $this->remoteAdmin = $this->activityPubManager->findActorOrCreate("@remoteAdmin@$this->remoteDomain");
        $this->magazineManager->subscribe($this->localMagazine, $this->remoteUser);
    }

    protected function setUpRemoteActors(): void
    {
        parent::setUpRemoteActors();
        $user = $this->getUserByUsername('remoteAdmin', addImage: false);
        $this->registerActor($user, $this->remoteDomain, true);
        $this->remoteAdmin = $user;
    }

    public function setupLocalActors(): void
    {
        $this->localSubscriber = $this->getUserByUsername('localSubscriber', addImage: false);
        parent::setupLocalActors();
    }

    public function setUpRemoteEntities(): void
    {
        $this->buildBlockLocalUserLocalMagazine();
        $this->buildBlockLocalUserRemoteMagazine();
        $this->buildBlockRemoteUserLocalMagazine();
        $this->buildBlockRemoteUserRemoteMagazine();
        $this->buildInstanceBanRemoteUser();
    }

    public function testBlockLocalUserLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->blockLocalUserLocalMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->localMagazine, 'user' => $this->localSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->localMagazine);
        self::assertTrue($this->localMagazine->isBanned($this->localSubscriber));

        // should not be sent to source instance, only to subscriber instance
        $announcedBlock = $this->assertOneSentAnnouncedActivityOfTypeGetInnerActivity('Block', announcedActivityId: $this->blockLocalUserLocalMagazine['id'], inboxUrl: $this->remoteUser->apInboxUrl);
        self::assertEquals($this->blockLocalUserLocalMagazine['object'], $announcedBlock['object']);
    }

    #[Depends('testBlockLocalUserLocalMagazine')]
    public function testUndoBlockLocalUserLocalMagazine(): void
    {
        $this->testBlockLocalUserLocalMagazine();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoBlockLocalUserLocalMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->localMagazine, 'user' => $this->localSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->localMagazine);
        self::assertFalse($this->localMagazine->isBanned($this->localSubscriber));

        // should not be sent to source instance, only to subscriber instance
        $announcedUndo = $this->assertOneSentAnnouncedActivityOfTypeGetInnerActivity('Undo', announcedActivityId: $this->undoBlockLocalUserLocalMagazine['id'], inboxUrl: $this->remoteUser->apInboxUrl);
        self::assertEquals($this->undoBlockLocalUserLocalMagazine['object']['object'], $announcedUndo['object']['object']);
        self::assertEquals($this->undoBlockLocalUserLocalMagazine['object']['id'], $announcedUndo['object']['id']);
    }

    public function testBlockRemoteUserLocalMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->blockRemoteUserLocalMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->localMagazine, 'user' => $this->remoteUser]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->localMagazine);
        self::assertTrue($this->localMagazine->isBanned($this->remoteUser));

        // should not be sent to source instance, only to subscriber instance
        $blockActivity = $this->assertOneSentAnnouncedActivityOfTypeGetInnerActivity('Block', announcedActivityId: $this->blockRemoteUserLocalMagazine['id'], inboxUrl: $this->remoteUser->apInboxUrl);
        self::assertEquals($this->blockRemoteUserLocalMagazine['object'], $blockActivity['object']);
    }

    #[Depends('testBlockRemoteUserLocalMagazine')]
    public function testUndoBlockRemoteUserLocalMagazine(): void
    {
        $this->testBlockRemoteUserLocalMagazine();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoBlockRemoteUserLocalMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->localMagazine, 'user' => $this->remoteUser]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->localMagazine);
        self::assertFalse($this->localMagazine->isBanned($this->remoteUser));

        // should not be sent to source instance, only to subscriber instance
        $announcedUndo = $this->assertOneSentAnnouncedActivityOfTypeGetInnerActivity('Undo', announcedActivityId: $this->undoBlockRemoteUserLocalMagazine['id'], inboxUrl: $this->remoteUser->apInboxUrl);
        self::assertEquals($this->undoBlockRemoteUserLocalMagazine['object']['id'], $announcedUndo['object']['id']);
        self::assertEquals($this->undoBlockRemoteUserLocalMagazine['object']['object'], $announcedUndo['object']['object']);
    }

    public function testBlockLocalUserRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->blockLocalUserRemoteMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->remoteMagazine, 'user' => $this->localSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->remoteMagazine);
        self::assertTrue($this->remoteMagazine->isBanned($this->localSubscriber));
    }

    #[Depends('testBlockLocalUserRemoteMagazine')]
    public function testUndoBlockLocalUserRemoteMagazine(): void
    {
        $this->testBlockLocalUserRemoteMagazine();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoBlockLocalUserRemoteMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->remoteMagazine, 'user' => $this->localSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->remoteMagazine);
        self::assertFalse($this->remoteMagazine->isBanned($this->localSubscriber));
    }

    public function testBlockRemoteUserRemoteMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->blockRemoteUserRemoteMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->remoteMagazine, 'user' => $this->remoteSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->remoteMagazine);
        self::assertTrue($this->remoteMagazine->isBanned($this->remoteSubscriber));
    }

    #[Depends('testBlockRemoteUserRemoteMagazine')]
    public function testUndoBlockRemoteUserRemoteMagazine(): void
    {
        $this->testBlockRemoteUserRemoteMagazine();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoBlockRemoteUserRemoteMagazine)));
        $ban = $this->magazineBanRepository->findOneBy(['magazine' => $this->remoteMagazine, 'user' => $this->remoteSubscriber]);
        self::assertNotNull($ban);
        $this->entityManager->refresh($this->remoteMagazine);
        self::assertFalse($this->remoteMagazine->isBanned($this->remoteSubscriber));
    }

    public function testInstanceBanRemoteUser(): void
    {
        $username = "@remoteUser@$this->remoteDomain";
        $remoteUser = $this->userRepository->findOneByUsername($username);
        self::assertFalse($remoteUser->isBanned);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->instanceBanRemoteUser)));
        $this->entityManager->refresh($remoteUser);
        self::assertTrue($remoteUser->isBanned);
        self::assertEquals('testing', $remoteUser->banReason);
    }

    #[Depends('testInstanceBanRemoteUser')]
    public function testUndoInstanceBanRemoteUser(): void
    {
        $this->testInstanceBanRemoteUser();
        $username = "@remoteUser@$this->remoteDomain";
        $remoteUser = $this->userRepository->findOneByUsername($username);
        self::assertTrue($remoteUser->isBanned);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoInstanceBanRemoteUser)));
        $this->entityManager->refresh($remoteUser);
        self::assertFalse($remoteUser->isBanned);
    }

    private function buildBlockLocalUserLocalMagazine(): void
    {
        $ban = $this->magazineManager->ban($this->localMagazine, $this->localSubscriber, $this->remoteSubscriber, MagazineBanDto::create('testing'));

        $activity = $this->blockFactory->createActivityFromMagazineBan($ban);
        $this->blockLocalUserLocalMagazine = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->blockLocalUserLocalMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->blockLocalUserLocalMagazine['actor']);
        $this->blockLocalUserLocalMagazine['object'] = str_replace($this->remoteDomain, $this->localDomain, $this->blockLocalUserLocalMagazine['object']);
        $this->blockLocalUserLocalMagazine['target'] = str_replace($this->remoteDomain, $this->localDomain, $this->blockLocalUserLocalMagazine['target']);

        $undoActivity = $this->undoWrapper->build($activity);
        $this->undoBlockLocalUserLocalMagazine = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $this->undoBlockLocalUserLocalMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->undoBlockLocalUserLocalMagazine['actor']);
        $this->undoBlockLocalUserLocalMagazine['object'] = $this->blockLocalUserLocalMagazine;

        $this->testingApHttpClient->activityObjects[$this->blockLocalUserLocalMagazine['id']] = $this->blockLocalUserLocalMagazine;
        $this->testingApHttpClient->activityObjects[$this->undoBlockLocalUserLocalMagazine['id']] = $this->undoBlockLocalUserLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $activity;
    }

    private function buildBlockRemoteUserLocalMagazine(): void
    {
        $ban = $this->magazineManager->ban($this->localMagazine, $this->remoteUser, $this->remoteSubscriber, MagazineBanDto::create('testing'));

        $activity = $this->blockFactory->createActivityFromMagazineBan($ban);
        $this->blockRemoteUserLocalMagazine = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->blockRemoteUserLocalMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->blockRemoteUserLocalMagazine['actor']);
        $this->blockRemoteUserLocalMagazine['target'] = str_replace($this->remoteDomain, $this->localDomain, $this->blockRemoteUserLocalMagazine['target']);

        $undoActivity = $this->undoWrapper->build($activity);
        $this->undoBlockRemoteUserLocalMagazine = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $this->undoBlockRemoteUserLocalMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->undoBlockRemoteUserLocalMagazine['actor']);
        $this->undoBlockRemoteUserLocalMagazine['object'] = $this->blockRemoteUserLocalMagazine;

        $this->testingApHttpClient->activityObjects[$this->blockRemoteUserLocalMagazine['id']] = $this->blockRemoteUserLocalMagazine;
        $this->testingApHttpClient->activityObjects[$this->undoBlockRemoteUserLocalMagazine['id']] = $this->undoBlockRemoteUserLocalMagazine;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $activity;
    }

    private function buildBlockLocalUserRemoteMagazine(): void
    {
        $ban = $this->magazineManager->ban($this->remoteMagazine, $this->localSubscriber, $this->remoteSubscriber, MagazineBanDto::create('testing'));

        $activity = $this->blockFactory->createActivityFromMagazineBan($ban);
        $this->blockLocalUserRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->blockLocalUserRemoteMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->blockLocalUserRemoteMagazine['actor']);
        $this->blockLocalUserRemoteMagazine['object'] = str_replace($this->remoteDomain, $this->localDomain, $this->blockLocalUserRemoteMagazine['object']);

        $undoActivity = $this->undoWrapper->build($activity);
        $this->undoBlockLocalUserRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $this->undoBlockLocalUserRemoteMagazine['actor'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->undoBlockLocalUserRemoteMagazine['actor']);
        $this->undoBlockLocalUserRemoteMagazine['object'] = $this->blockLocalUserRemoteMagazine;

        $this->testingApHttpClient->activityObjects[$this->blockLocalUserRemoteMagazine['id']] = $this->blockLocalUserRemoteMagazine;
        $this->testingApHttpClient->activityObjects[$this->undoBlockLocalUserRemoteMagazine['id']] = $this->undoBlockLocalUserRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $activity;
    }

    private function buildBlockRemoteUserRemoteMagazine(): void
    {
        $ban = $this->magazineManager->ban($this->remoteMagazine, $this->remoteSubscriber, $this->remoteUser, MagazineBanDto::create('testing'));

        $activity = $this->blockFactory->createActivityFromMagazineBan($ban);
        $this->blockRemoteUserRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->blockRemoteUserRemoteMagazine['object'] = str_replace($this->remoteDomain, $this->remoteSubDomain, $this->blockRemoteUserRemoteMagazine['object']);

        $undoActivity = $this->undoWrapper->build($activity);
        $this->undoBlockRemoteUserRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($undoActivity);
        $this->undoBlockRemoteUserRemoteMagazine['object'] = $this->blockRemoteUserRemoteMagazine;

        $this->testingApHttpClient->activityObjects[$this->blockRemoteUserRemoteMagazine['id']] = $this->blockRemoteUserRemoteMagazine;
        $this->testingApHttpClient->activityObjects[$this->undoBlockRemoteUserRemoteMagazine['id']] = $this->undoBlockRemoteUserRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $undoActivity;
        $this->entitiesToRemoveAfterSetup[] = $activity;
    }

    private function buildInstanceBanRemoteUser(): void
    {
        $this->remoteUser->banReason = 'testing';
        $activity = $this->blockFactory->createActivityFromInstanceBan($this->remoteUser, $this->remoteAdmin);
        $this->instanceBanRemoteUser = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->testingApHttpClient->activityObjects[$this->instanceBanRemoteUser['id']] = $this->instanceBanRemoteUser;
        $this->entitiesToRemoveAfterSetup[] = $activity;

        $activity = $this->undoWrapper->build($activity);
        $this->undoInstanceBanRemoteUser = $this->activityJsonBuilder->buildActivityJson($activity);
        $this->testingApHttpClient->activityObjects[$this->undoInstanceBanRemoteUser['id']] = $this->undoInstanceBanRemoteUser;
        $this->entitiesToRemoveAfterSetup[] = $activity;
    }
}

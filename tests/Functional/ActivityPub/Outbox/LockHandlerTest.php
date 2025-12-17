<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Outbox;

use App\DTO\ModeratorDto;
use App\Entity\User;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class LockHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $createRemoteEntryInLocalMagazine;
    private array $createRemoteEntryInRemoteMagazine;
    private array $createRemotePostInLocalMagazine;
    private array $createRemotePostInRemoteMagazine;
    private User $remotePoster;
    private User $localPoster;

    public function setUp(): void
    {
        parent::setUp();
        $this->magazineManager->addModerator(new ModeratorDto($this->remoteMagazine, $this->localUser));
        $this->localPoster = $this->getUserByUsername('localPoster', addImage: false);
    }

    public function setUpRemoteEntities(): void
    {
        $this->createRemoteEntryInRemoteMagazine = $this->createRemoteEntryInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemotePostInRemoteMagazine = $this->createRemotePostInRemoteMagazine($this->remoteMagazine, $this->remotePoster);
        $this->createRemoteEntryInLocalMagazine = $this->createRemoteEntryInLocalMagazine($this->localMagazine, $this->remotePoster);
        $this->createRemotePostInLocalMagazine = $this->createRemotePostInLocalMagazine($this->localMagazine, $this->remotePoster);
    }

    protected function setUpRemoteActors(): void
    {
        parent::setUpRemoteActors();
        $username = 'remotePoster';
        $domain = $this->remoteDomain;
        $this->remotePoster = $this->getUserByUsername($username, addImage: false);
        $this->registerActor($this->remotePoster, $domain, true);
    }

    public function testLockLocalEntryInLocalMagazineByLocalModerator(): void
    {
        $entry = $this->createEntry('Some local entry', $this->localMagazine, $this->localPoster);
        $this->entryManager->toggleLock($entry, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockLocalEntryInRemoteMagazineByLocalModerator(): void
    {
        $entry = $this->createEntry('Some local entry', $this->remoteMagazine, $this->localPoster);
        $this->entryManager->toggleLock($entry, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockRemoteEntryInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInLocalMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInLocalMagazine['object']['id']]);
        self::assertNotNull($entry);
        $this->entryManager->toggleLock($entry, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockRemoteEntryInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemoteEntryInRemoteMagazine)));
        $entry = $this->entryRepository->findOneBy(['apId' => $this->createRemoteEntryInRemoteMagazine['object']['object']['id']]);
        self::assertNotNull($entry);
        $this->entryManager->toggleLock($entry, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockLocalPostInLocalMagazineByLocalModerator(): void
    {
        $post = $this->createPost('Some post', $this->localMagazine, $this->localPoster);
        $this->postManager->toggleLock($post, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockLocalPostInRemoteMagazineByLocalModerator(): void
    {
        $post = $this->createPost('Some post', $this->remoteMagazine, $this->localPoster);
        $this->postManager->toggleLock($post, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockRemotePostInLocalMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInLocalMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createRemotePostInLocalMagazine['object']['id']]);
        self::assertNotNull($post);
        $this->postManager->toggleLock($post, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockRemotePostInRemoteMagazineByLocalModerator(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->createRemotePostInRemoteMagazine)));
        $post = $this->postRepository->findOneBy(['apId' => $this->createRemotePostInRemoteMagazine['object']['object']['id']]);
        self::assertNotNull($post);
        $this->postManager->toggleLock($post, $this->localUser);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockLocalEntryInRemoteMagazineByAuthor(): void
    {
        $entry = $this->createEntry('Some local entry', $this->remoteMagazine, $this->localPoster);
        $this->entryManager->toggleLock($entry, $this->localPoster);
        $this->assertOneSentActivityOfType('Lock');
    }

    public function testLockLocalPostInRemoteMagazineByAuthor(): void
    {
        $post = $this->createPost('Some local post', $this->remoteMagazine, $this->localPoster);
        $this->postManager->toggleLock($post, $this->localPoster);
        $this->assertOneSentActivityOfType('Lock');
    }
}

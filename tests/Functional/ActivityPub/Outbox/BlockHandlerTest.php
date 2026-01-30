<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Outbox;

use App\DTO\MagazineBanDto;
use App\Entity\User;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class BlockHandlerTest extends ActivityPubFunctionalTestCase
{
    private User $localSubscriber;

    public function setUp(): void
    {
        parent::setUp();
        $this->localSubscriber = $this->getUserByUsername('localSubscriber', addImage: false);
        // so localSubscriber has one interaction with another instance
        $this->magazineManager->subscribe($this->remoteMagazine, $this->localSubscriber);
    }

    public function setUpRemoteEntities(): void
    {
    }

    public function testBanLocalUserLocalMagazineLocalModerator(): void
    {
        $this->magazineManager->ban($this->localMagazine, $this->localSubscriber, $this->localUser, MagazineBanDto::create(reason: 'test'));

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        self::assertEquals('test', $blockActivity['summary']);
        self::assertEquals($this->personFactory->getActivityPubId($this->localSubscriber), $blockActivity['object']);
        self::assertEquals($this->groupFactory->getActivityPubId($this->localMagazine), $blockActivity['target']);
    }

    public function testUndoBanLocalUserLocalMagazineLocalModerator(): void
    {
        $this->magazineManager->ban($this->localMagazine, $this->localSubscriber, $this->localUser, MagazineBanDto::create(reason: 'test'));

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        $this->magazineManager->unban($this->localMagazine, $this->localSubscriber);
        $undoActivity = $this->assertOneSentActivityOfType('Undo', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        self::assertEquals($blockActivity['id'], $undoActivity['object']['id']);
    }

    public function testBanRemoteUserLocalMagazineLocalModerator(): void
    {
        $this->magazineManager->ban($this->localMagazine, $this->remoteSubscriber, $this->localUser, MagazineBanDto::create(reason: 'test'));

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        self::assertEquals('test', $blockActivity['summary']);
        self::assertEquals($this->remoteSubscriber->apProfileId, $blockActivity['object']);
        self::assertEquals($this->groupFactory->getActivityPubId($this->localMagazine), $blockActivity['target']);
    }

    public function testUndoBanRemoteUserLocalMagazineLocalModerator(): void
    {
        $this->magazineManager->ban($this->localMagazine, $this->remoteSubscriber, $this->localUser, MagazineBanDto::create(reason: 'test'));

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        $this->magazineManager->unban($this->localMagazine, $this->remoteSubscriber);
        $undoActivity = $this->assertOneSentActivityOfType('Undo', inboxUrl: $this->remoteSubscriber->apInboxUrl);
        self::assertEquals($blockActivity['id'], $undoActivity['object']['id']);
    }

    public function testBanLocalUserInstanceLocalModerator(): void
    {
        $this->userManager->ban($this->localSubscriber, $this->localUser, 'test');

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteMagazine->apInboxUrl);
        self::assertEquals('test', $blockActivity['summary']);
        self::assertEquals($this->personFactory->getActivityPubId($this->localSubscriber), $blockActivity['object']);
        self::assertEquals($this->instanceFactory->getTargetUrl(), $blockActivity['target']);
    }

    public function testUndoBanLocalUserInstanceLocalModerator(): void
    {
        $this->userManager->ban($this->localSubscriber, $this->localUser, 'test');

        $blockActivity = $this->assertOneSentActivityOfType('Block', inboxUrl: $this->remoteMagazine->apInboxUrl);
        $this->userManager->unban($this->localSubscriber, $this->localUser, 'test');
        $undoActivity = $this->assertOneSentActivityOfType('Undo', inboxUrl: $this->remoteMagazine->apInboxUrl);
        self::assertEquals($blockActivity['id'], $undoActivity['object']['id']);
    }
}

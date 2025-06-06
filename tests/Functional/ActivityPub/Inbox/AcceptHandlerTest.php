<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class AcceptHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $followRemoteUser;
    private array $acceptFollowRemoteUser;
    private array $followRemoteMagazine;
    private array $acceptFollowRemoteMagazine;

    public function setUp(): void
    {
        parent::setUp();
        $this->remoteUser->apManuallyApprovesFollowers = true;
        $this->userManager->follow($this->localUser, $this->remoteUser);
    }

    public function setUpLocalEntities(): void
    {
        $followActivity = $this->followWrapper->build($this->localUser, $this->remoteUser);
        $this->followRemoteUser = $this->activityJsonBuilder->buildActivityJson($followActivity);
        $this->testingApHttpClient->activityObjects[$this->followRemoteUser['id']] = $this->followRemoteUser;
        $this->entitiesToRemoveAfterSetup[] = $followActivity;

        $this->magazineManager->subscribe($this->remoteMagazine, $this->localUser);
        $followActivity = $this->followWrapper->build($this->localUser, $this->remoteMagazine);
        $this->followRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($followActivity);
        $this->testingApHttpClient->activityObjects[$this->followRemoteMagazine['id']] = $this->followRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $followActivity;
    }

    public function setUpRemoteEntities(): void
    {
    }

    public function setUpLateRemoteEntities(): void
    {
        $acceptActivity = $this->followResponseWrapper->build($this->remoteUser, $this->followRemoteUser);
        $this->acceptFollowRemoteUser = $this->activityJsonBuilder->buildActivityJson($acceptActivity);
        $this->testingApHttpClient->activityObjects[$this->acceptFollowRemoteUser['id']] = $this->acceptFollowRemoteUser;
        $this->entitiesToRemoveAfterSetup[] = $acceptActivity;

        $acceptActivity = $this->followResponseWrapper->build($this->remoteMagazine, $this->followRemoteMagazine);
        $this->acceptFollowRemoteMagazine = $this->activityJsonBuilder->buildActivityJson($acceptActivity);
        $this->testingApHttpClient->activityObjects[$this->acceptFollowRemoteMagazine['id']] = $this->acceptFollowRemoteMagazine;
        $this->entitiesToRemoveAfterSetup[] = $acceptActivity;
    }

    public function testAcceptFollowMagazine(): void
    {
        // we do not have manual follower approving for magazines implemented
        $this->bus->dispatch(new ActivityMessage(json_encode($this->acceptFollowRemoteMagazine)));
    }

    public function testAcceptFollowUser(): void
    {
        self::assertTrue($this->remoteUser->apManuallyApprovesFollowers);
        $request = $this->userFollowRequestRepository->findOneby(['follower' => $this->localUser, 'following' => $this->remoteUser]);
        self::assertNotNull($request);
        $this->bus->dispatch(new ActivityMessage(json_encode($this->acceptFollowRemoteUser)));

        $request = $this->userFollowRequestRepository->findOneby(['follower' => $this->localUser, 'following' => $this->remoteUser]);
        self::assertNull($request);
    }
}

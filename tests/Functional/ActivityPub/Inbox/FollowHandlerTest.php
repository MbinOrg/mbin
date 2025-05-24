<?php

declare(strict_types=1);

namespace App\Tests\Functional\ActivityPub\Inbox;

use App\ActivityPub\JsonRd;
use App\Event\ActivityPub\WebfingerResponseEvent;
use App\Message\ActivityPub\Inbox\ActivityMessage;
use App\Service\ActivityPub\Webfinger\WebFingerFactory;
use App\Tests\Functional\ActivityPub\ActivityPubFunctionalTestCase;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;

#[Group(name: 'ActivityPub')]
#[Group(name: 'NonThreadSafe')]
class FollowHandlerTest extends ActivityPubFunctionalTestCase
{
    private array $userFollowMagazine;
    private array $undoUserFollowMagazine;
    private array $userFollowUser;
    private array $undoUserFollowUser;
    private string $followUserApId;

    public function setUpRemoteEntities(): void
    {
        $domain = $this->remoteDomain;
        $username = 'followUser';
        $followUser = $this->getUserByUsername('followUser');
        $json = $this->personFactory->create($followUser);
        $this->testingApHttpClient->actorObjects[$json['id']] = $json;
        $this->followUserApId = $this->personFactory->getActivityPubId($followUser);

        $userEvent = new WebfingerResponseEvent(new JsonRd(), "acct:$username@$domain", ['account' => $username]);
        $this->eventDispatcher->dispatch($userEvent);
        $realDomain = \sprintf(WebFingerFactory::WEBFINGER_URL, 'https', $domain, '', "$username@$domain");
        $this->testingApHttpClient->webfingerObjects[$realDomain] = $userEvent->jsonRd->toArray();

        $followActivity = $this->followWrapper->build($followUser, $this->localMagazine);
        $this->userFollowMagazine = $this->activityJsonBuilder->buildActivityJson($followActivity);
        $apId = "https://$this->prev/m/{$this->localMagazine->name}";
        $this->userFollowMagazine['object'] = $apId;
        $this->userFollowMagazine['to'] = [$apId];
        $this->testingApHttpClient->activityObjects[$this->userFollowMagazine['id']] = $this->userFollowMagazine;

        $undoFollowActivity = $this->undoWrapper->build($followActivity);
        $this->undoUserFollowMagazine = $this->activityJsonBuilder->buildActivityJson($undoFollowActivity);
        $this->undoUserFollowMagazine['to'] = [$apId];
        $this->undoUserFollowMagazine['object']['to'] = $apId;
        $this->undoUserFollowMagazine['object']['object'] = $apId;
        $this->testingApHttpClient->activityObjects[$this->undoUserFollowMagazine['id']] = $this->undoUserFollowMagazine;

        $followActivity2 = $this->followWrapper->build($followUser, $this->localUser);
        $this->userFollowUser = $this->activityJsonBuilder->buildActivityJson($followActivity2);
        $apId = "https://$this->prev/u/{$this->localUser->username}";
        $this->userFollowUser['object'] = $apId;
        $this->userFollowUser['to'] = [$apId];
        $this->testingApHttpClient->activityObjects[$this->userFollowUser['id']] = $this->userFollowUser;

        $undoFollowActivity2 = $this->undoWrapper->build($followActivity2);
        $this->undoUserFollowUser = $this->activityJsonBuilder->buildActivityJson($undoFollowActivity2);
        $this->undoUserFollowUser['to'] = [$apId];
        $this->undoUserFollowUser['object']['to'] = $apId;
        $this->undoUserFollowUser['object']['object'] = $apId;
        $this->testingApHttpClient->activityObjects[$this->undoUserFollowUser['id']] = $this->undoUserFollowUser;

        $this->entitiesToRemoveAfterSetup[] = $undoFollowActivity2;
        $this->entitiesToRemoveAfterSetup[] = $followActivity2;
        $this->entitiesToRemoveAfterSetup[] = $undoFollowActivity;
        $this->entitiesToRemoveAfterSetup[] = $followActivity;
        $this->entitiesToRemoveAfterSetup[] = $followUser;
    }

    public function testUserFollowUser(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->userFollowUser)));
        $this->entityManager->refresh($this->localUser);
        $followUser = $this->userRepository->findOneBy(['apProfileId' => $this->followUserApId]);
        $this->entityManager->refresh($followUser);

        self::assertNotNull($followUser);
        self::assertTrue($followUser->isFollower($this->localUser));
        self::assertTrue($followUser->isFollowing($this->localUser));
        self::assertNotNull($this->userFollowRepository->findOneBy(['follower' => $followUser, 'following' => $this->localUser]));
        self::assertNull($this->userFollowRepository->findOneBy(['follower' => $this->localUser, 'following' => $followUser]));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertCount(1, $postedObjects);
        self::assertEquals('Accept', $postedObjects[0]['payload']['type']);
        self::assertEquals($followUser->apInboxUrl, $postedObjects[0]['inboxUrl']);
        self::assertEquals($this->userFollowUser['id'], $postedObjects[0]['payload']['object']['id']);
    }

    #[Depends('testUserFollowUser')]
    public function testUndoUserFollowUser(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->userFollowUser)));
        $followUser = $this->userRepository->findOneBy(['apProfileId' => $this->followUserApId]);
        $this->entityManager->refresh($followUser);
        $this->entityManager->refresh($this->localUser);
        $prevPostedObjects = $this->testingApHttpClient->getPostedObjects();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoUserFollowUser)));
        $this->entityManager->refresh($this->localUser);
        $this->entityManager->refresh($followUser);

        self::assertNotNull($followUser);
        self::assertFalse($followUser->isFollower($this->localUser));
        self::assertFalse($followUser->isFollowing($this->localUser));
        self::assertNull($this->userFollowRepository->findOneBy(['follower' => $followUser, 'following' => $this->localUser]));
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertEquals(0, \sizeof($prevPostedObjects) - \sizeof($postedObjects));
    }

    public function testUserFollowMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->userFollowMagazine)));
        $this->entityManager->refresh($this->localUser);
        $followUser = $this->userRepository->findOneBy(['apProfileId' => $this->followUserApId]);
        $this->entityManager->refresh($followUser);

        self::assertNotNull($followUser);
        $sub = $this->magazineSubscriptionRepository->findOneBy(['user' => $followUser, 'magazine' => $this->localMagazine]);
        self::assertNotNull($sub);
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertCount(1, $postedObjects);
        self::assertEquals('Accept', $postedObjects[0]['payload']['type']);
        self::assertEquals($followUser->apInboxUrl, $postedObjects[0]['inboxUrl']);
        self::assertEquals($this->userFollowMagazine['id'], $postedObjects[0]['payload']['object']['id']);
    }

    #[Depends('testUserFollowMagazine')]
    public function testUndoUserFollowMagazine(): void
    {
        $this->bus->dispatch(new ActivityMessage(json_encode($this->userFollowMagazine)));
        $followUser = $this->userRepository->findOneBy(['apProfileId' => $this->followUserApId]);
        $this->entityManager->refresh($followUser);
        $this->entityManager->refresh($this->localUser);
        $prevPostedObjects = $this->testingApHttpClient->getPostedObjects();
        $this->bus->dispatch(new ActivityMessage(json_encode($this->undoUserFollowMagazine)));
        $this->entityManager->refresh($this->localUser);
        $this->entityManager->refresh($followUser);

        self::assertNotNull($followUser);
        $sub = $this->magazineSubscriptionRepository->findOneBy(['magazine' => $this->localMagazine, 'user' => $followUser]);
        self::assertNull($sub);
        $postedObjects = $this->testingApHttpClient->getPostedObjects();
        self::assertEquals(0, \sizeof($prevPostedObjects) - \sizeof($postedObjects));
    }
}

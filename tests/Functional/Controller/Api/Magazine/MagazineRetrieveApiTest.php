<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\Service\MagazineManager;
use App\Tests\WebTestCase;

class MagazineRetrieveApiTest extends WebTestCase
{
    public const MODERATOR_RESPONSE_KEYS = [
        'magazineId',
        'userId',
        'username',
        'avatar',
        'apId',
    ];

    public const MAGAZINE_COUNT = 20;

    public function testApiCanRetrieveMagazineByIdAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}");

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertSame($magazine->getId(), $jsonData['magazineId']);
        self::assertIsArray($jsonData['owner']);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['owner']);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['owner']['userId']);
        self::assertNull($jsonData['icon']);
        self::assertNull($jsonData['banner']);
        self::assertEmpty($jsonData['tags']);
        self::assertEquals('test', $jsonData['name']);
        self::assertIsArray($jsonData['badges']);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(1, $jsonData['moderators']);
        self::assertIsArray($jsonData['moderators'][0]);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][0]);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['moderators'][0]['userId']);

        self::assertFalse($jsonData['isAdult']);
        // Anonymous access, so these values should be null
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveMagazineById(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertSame($magazine->getId(), $jsonData['magazineId']);
        self::assertIsArray($jsonData['owner']);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['owner']);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['owner']['userId']);
        self::assertNull($jsonData['icon']);
        self::assertNull($jsonData['banner']);
        self::assertEmpty($jsonData['tags']);
        self::assertEquals('test', $jsonData['name']);
        self::assertIsArray($jsonData['badges']);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(1, $jsonData['moderators']);
        self::assertIsArray($jsonData['moderators'][0]);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][0]);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['moderators'][0]['userId']);

        self::assertFalse($jsonData['isAdult']);
        // Scopes for reading subscriptions and blocklists not granted, so these values should be null
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveMagazineByNameAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('GET', '/api/magazine/name/test');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertSame($magazine->getId(), $jsonData['magazineId']);
        self::assertIsArray($jsonData['owner']);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['owner']);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['owner']['userId']);
        self::assertNull($jsonData['icon']);
        self::assertNull($jsonData['banner']);
        self::assertEmpty($jsonData['tags']);
        self::assertEquals('test', $jsonData['name']);
        self::assertIsArray($jsonData['badges']);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(1, $jsonData['moderators']);
        self::assertIsArray($jsonData['moderators'][0]);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][0]);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['moderators'][0]['userId']);

        self::assertFalse($jsonData['isAdult']);
        // Anonymous access, so these values should be null
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveMagazineByName(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazine/name/test', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertSame($magazine->getId(), $jsonData['magazineId']);
        self::assertIsArray($jsonData['owner']);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['owner']);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['owner']['userId']);
        self::assertNull($jsonData['icon']);
        self::assertNull($jsonData['banner']);
        self::assertEmpty($jsonData['tags']);
        self::assertEquals('test', $jsonData['name']);
        self::assertIsArray($jsonData['badges']);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(1, $jsonData['moderators']);
        self::assertIsArray($jsonData['moderators'][0]);
        self::assertArrayKeysMatch(self::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][0]);
        self::assertSame($magazine->getOwner()->getId(), $jsonData['moderators'][0]['userId']);

        self::assertFalse($jsonData['isAdult']);
        // Scopes for reading subscriptions and blocklists not granted, so these values should be null
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiMagazineSubscribeAndBlockFlags(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertFalse($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }

    // The 2 next tests exist because changing the subscription status via MagazineManager after calling the API
    //      was causing strange doctrine exceptions. If doctrine did not throw exceptions when modifications
    //      were made, these tests could be rolled into testApiMagazineSubscribeAndBlockFlags above
    public function testApiMagazineSubscribeFlagIsTrueWhenSubscribed(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $manager = $this->magazineManager;
        $manager->subscribe($magazine, $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertTrue($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }

    public function testApiMagazineBlockFlagIsTrueWhenBlocked(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $manager = $this->magazineManager;
        $manager->block($magazine, $user);
        $entityManager = $this->entityManager;
        $entityManager->persist($user);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertFalse($jsonData['isUserSubscribed']);
        self::assertTrue($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveMagazineCollectionAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('GET', '/api/magazines');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
    }

    public function testApiCanRetrieveMagazineCollection(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
        // Scopes not granted
        self::assertNull($jsonData['items'][0]['isUserSubscribed']);
        self::assertNull($jsonData['items'][0]['isBlockedByUser']);
    }

    public function testApiCanRetrieveMagazineCollectionMultiplePages(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazines = [];
        for ($i = 0; $i < self::MAGAZINE_COUNT; ++$i) {
            $magazines[] = $this->getMagazineByNameNoRSAKey("test{$i}");
        }
        $perPage = max((int) ceil(self::MAGAZINE_COUNT / 2), 1);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazines?perPage={$perPage}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(self::MAGAZINE_COUNT, $jsonData['pagination']['count']);
        self::assertSame($perPage, $jsonData['pagination']['perPage']);
        self::assertSame(1, $jsonData['pagination']['currentPage']);
        self::assertSame(2, $jsonData['pagination']['maxPage']);
        self::assertIsArray($jsonData['items']);
        self::assertCount($perPage, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertAllValuesFoundByName($magazines, $jsonData['items']);
    }

    public function testApiCannotRetrieveMagazineSubscriptionsAnonymous(): void
    {
        $this->client->request('GET', '/api/magazines/subscribed');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveMagazineSubscriptionsWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveMagazineSubscriptions(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $notSubbedMag = $this->getMagazineByName('someother', $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
        // Block scope not granted
        self::assertTrue($jsonData['items'][0]['isUserSubscribed']);
        self::assertNull($jsonData['items'][0]['isBlockedByUser']);
    }

    public function testApiCannotRetrieveUserMagazineSubscriptionsAnonymous(): void
    {
        $user = $this->getUserByUsername('testUser');
        $this->client->request('GET', "/api/users/{$user->getId()}/magazines/subscriptions");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveUserMagazineSubscriptionsWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $user = $this->getUserByUsername('testUser');
        $this->client->request('GET', "/api/users/{$user->getId()}/magazines/subscriptions", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveUserMagazineSubscriptions(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $user = $this->getUserByUsername('testUser');
        $user->showProfileSubscriptions = true;
        $entityManager = $this->entityManager;
        $entityManager->persist($user);
        $entityManager->flush();

        $notSubbedMag = $this->getMagazineByName('someother', $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('test', $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/magazines/subscriptions", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
        // Block scope not granted
        self::assertFalse($jsonData['items'][0]['isUserSubscribed']);
        self::assertNull($jsonData['items'][0]['isBlockedByUser']);
    }

    public function testApiCannotRetrieveUserMagazineSubscriptionsIfSettingTurnedOff(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $user = $this->getUserByUsername('testUser');
        $user->showProfileSubscriptions = false;
        $entityManager = $this->entityManager;
        $entityManager->persist($user);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/magazines/subscriptions", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRetrieveModeratedMagazinesAnonymous(): void
    {
        $this->client->request('GET', '/api/magazines/moderated');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveModeratedMagazinesWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/moderated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveModeratedMagazines(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $notModdedMag = $this->getMagazineByName('someother', $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:list');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/moderated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
        // Subscribe and block scopes not granted
        self::assertNull($jsonData['items'][0]['isUserSubscribed']);
        self::assertNull($jsonData['items'][0]['isBlockedByUser']);
    }

    public function testApiCannotRetrieveBlockedMagazinesAnonymous(): void
    {
        $this->client->request('GET', '/api/magazines/blocked');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveBlockedMagazinesWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/blocked', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveBlockedMagazines(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $notBlockedMag = $this->getMagazineByName('someother', $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));

        $manager = $this->magazineManager;
        $manager->block($magazine, $this->getUserByUsername('JohnDoe'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazines/blocked', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazineId']);
        // Subscribe and block scopes not granted
        self::assertNull($jsonData['items'][0]['isUserSubscribed']);
        self::assertTrue($jsonData['items'][0]['isBlockedByUser']);
    }

    public function testApiCanRetrieveAbandonedMagazine(): void
    {
        $abandoningUser = $this->getUserByUsername('JohnDoe');
        $activeUser = $this->getUserByUsername('DoeJohn');
        $magazine1 = $this->getMagazineByName('test1', $abandoningUser);
        $magazine2 = $this->getMagazineByName('test2', $abandoningUser);
        $magazine3 = $this->getMagazineByName('test3', $activeUser);

        $abandoningUser->lastActive = new \DateTime('-6 months');
        $activeUser->lastActive = new \DateTime('-2 days');
        $this->userRepository->save($abandoningUser, true);
        $this->userRepository->save($activeUser, true);

        $this->client->request('GET', '/api/magazines?abandoned=true&federation=local');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine1->getId(), $jsonData['items'][0]['magazineId']);
        self::assertSame($magazine2->getId(), $jsonData['items'][1]['magazineId']);
    }

    public function testApiCanRetrieveAbandonedMagazineSortedByOwner(): void
    {
        $abandoningUser1 = $this->getUserByUsername('user1');
        $abandoningUser2 = $this->getUserByUsername('user2');
        $abandoningUser3 = $this->getUserByUsername('user3');
        $magazine1 = $this->getMagazineByName('test1', $abandoningUser1);
        $magazine2 = $this->getMagazineByName('test2', $abandoningUser2);
        $magazine3 = $this->getMagazineByName('test3', $abandoningUser3);

        $abandoningUser1->lastActive = new \DateTime('-6 months');
        $abandoningUser2->lastActive = new \DateTime('-5 months');
        $abandoningUser3->lastActive = new \DateTime('-7 months');
        $this->userRepository->save($abandoningUser1, true);
        $this->userRepository->save($abandoningUser2, true);
        $this->userRepository->save($abandoningUser3, true);

        $this->client->request('GET', '/api/magazines?abandoned=true&federation=local&sort=ownerLastActive');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertArrayKeysMatch(self::MAGAZINE_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($magazine1->getId(), $jsonData['items'][1]['magazineId']);
        self::assertSame($magazine2->getId(), $jsonData['items'][2]['magazineId']);
        self::assertSame($magazine3->getId(), $jsonData['items'][0]['magazineId']);
    }

    public static function assertAllValuesFoundByName(array $magazines, array $values, string $message = '')
    {
        $nameMap = array_column($magazines, null, 'name');
        $containsMagazine = fn (bool $result, array $item) => $result && null !== $nameMap[$item['name']];
        self::assertTrue(array_reduce($values, $containsMagazine, true), $message);
    }
}

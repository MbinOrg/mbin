<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\Repository\UserRepository;
use App\Tests\WebTestCase;

class UserRetrieveApiTest extends WebTestCase
{
    public const USER_SETTINGS_KEYS = [
        'notifyOnNewEntry',
        'notifyOnNewEntryReply',
        'notifyOnNewEntryCommentReply',
        'notifyOnNewPost',
        'notifyOnNewPostReply',
        'notifyOnNewPostCommentReply',
        'hideAdult',
        'showProfileSubscriptions',
        'showProfileFollowings',
        'addMentionsEntries',
        'addMentionsPosts',
        'homepage',
        'featuredMagazines',
        'preferredLanguages',
        'customCss',
        'ignoreMagazinesCustomCss',
    ];
    public const NUM_USERS = 10;

    public function testApiCanRetrieveUsersWithAboutAnonymous(): void
    {
        $users = [];
        for ($i = 0; $i < self::NUM_USERS; ++$i) {
            $users[] = $this->getUserByUsername('user'.(string) ($i + 1), about: 'Test user '.(string) ($i + 1));
        }

        $this->client->request('GET', '/api/users');
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(self::NUM_USERS, $jsonData['pagination']['count']);
        self::assertSame(1, $jsonData['pagination']['currentPage']);
        self::assertSame(1, $jsonData['pagination']['maxPage']);
        // Default perPage count should be used since no perPage value was specified
        self::assertSame(UserRepository::PER_PAGE, $jsonData['pagination']['perPage']);

        self::assertIsArray($jsonData['items']);
        self::assertSame(self::NUM_USERS, \count($jsonData['items']));
    }

    public function testApiCanRetrieveUsersWithAbout(): void
    {
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('UserWithoutAbout'));
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $users = [];
        for ($i = 0; $i < self::NUM_USERS; ++$i) {
            $users[] = $this->getUserByUsername('user'.(string) ($i + 1), about: 'Test user '.(string) ($i + 1));
        }

        $this->client->request('GET', '/api/users', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(self::NUM_USERS, $jsonData['pagination']['count']);
    }

    public function testApiCanRetrieveUserByIdAnonymous(): void
    {
        $testUser = $this->getUserByUsername('UserWithoutAbout');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId());
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertSame('UserWithoutAbout', $jsonData['username']);
        self::assertNull($jsonData['about']);
        self::assertNotNull($jsonData['createdAt']);
        self::assertFalse($jsonData['isBot']);
        self::assertNull($jsonData['apId']);
        // Follow and block scopes not assigned, so these flags should be null
        self::assertNull($jsonData['isFollowedByUser']);
        self::assertNull($jsonData['isFollowerOfUser']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveUserById(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId(), server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertSame('UserWithoutAbout', $jsonData['username']);
        self::assertNull($jsonData['about']);
        self::assertNotNull($jsonData['createdAt']);
        self::assertFalse($jsonData['isBot']);
        self::assertNull($jsonData['apId']);
        // Follow and block scopes not assigned, so these flags should be null
        self::assertNull($jsonData['isFollowedByUser']);
        self::assertNull($jsonData['isFollowerOfUser']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveUserByNameAnonymous(): void
    {
        $testUser = $this->getUserByUsername('UserWithoutAbout');

        $this->client->request('GET', '/api/users/name/'.$testUser->getUsername());
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertSame('UserWithoutAbout', $jsonData['username']);
        self::assertNull($jsonData['about']);
        self::assertNotNull($jsonData['createdAt']);
        self::assertFalse($jsonData['isBot']);
        self::assertNull($jsonData['apId']);
        // Follow and block scopes not assigned, so these flags should be null
        self::assertNull($jsonData['isFollowedByUser']);
        self::assertNull($jsonData['isFollowerOfUser']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveUserByName(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/name/'.$testUser->getUsername(), server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertSame('UserWithoutAbout', $jsonData['username']);
        self::assertNull($jsonData['about']);
        self::assertNotNull($jsonData['createdAt']);
        self::assertFalse($jsonData['isBot']);
        self::assertNull($jsonData['apId']);
        // Follow and block scopes not assigned, so these flags should be null
        self::assertNull($jsonData['isFollowedByUser']);
        self::assertNull($jsonData['isFollowerOfUser']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCannotRetrieveCurrentUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveCurrentUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:read');

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        self::assertSame($testUser->getId(), $jsonData['userId']);
        self::assertSame('UserWithoutAbout', $jsonData['username']);
        self::assertNull($jsonData['about']);
        self::assertNotNull($jsonData['createdAt']);
        self::assertFalse($jsonData['isBot']);
        self::assertNull($jsonData['apId']);
        // Follow and block scopes not assigned, so these flags should be null
        self::assertNull($jsonData['isFollowedByUser']);
        self::assertNull($jsonData['isFollowerOfUser']);
        self::assertNull($jsonData['isBlockedByUser']);
    }

    public function testApiCanRetrieveUserFlagsWithScopes(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $follower = $this->getUserByUsername('follower');

        $follower->follow($testUser);

        $manager = $this->entityManager;

        $manager->persist($follower);
        $manager->flush();

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/'.(string) $follower->getId(), server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);
        // Follow and block scopes assigned, so these flags should not be null
        self::assertFalse($jsonData['isFollowedByUser']);
        self::assertTrue($jsonData['isFollowerOfUser']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }

    public function testApiCanGetBlockedUsers(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $blockedUser = $this->getUserByUsername('JohnDoe');

        $testUser->block($blockedUser);

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/blocked', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertSame(1, \count($jsonData['items']));
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($blockedUser->getId(), $jsonData['items'][0]['userId']);
    }

    public function testApiCannotGetFollowedUsersWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/followed', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotGetFollowersWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/followers', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanGetFollowedUsers(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followedUser = $this->getUserByUsername('JohnDoe');

        $testUser->follow($followedUser);

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/followed', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertSame(1, \count($jsonData['items']));
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($followedUser->getId(), $jsonData['items'][0]['userId']);
    }

    public function testApiCanGetFollowers(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followingUser = $this->getUserByUsername('JohnDoe');

        $followingUser->follow($testUser);

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/followers', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertSame(1, \count($jsonData['items']));
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($followingUser->getId(), $jsonData['items'][0]['userId']);
    }

    public function testApiCannotGetFollowedUsersByIdIfNotShared(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followedUser = $this->getUserByUsername('JohnDoe');

        $testUser->follow($followedUser);
        $testUser->showProfileFollowings = false;

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($followedUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId().'/followed', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanGetFollowedUsersById(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followedUser = $this->getUserByUsername('JohnDoe');

        $testUser->follow($followedUser);

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($followedUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId().'/followed', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertSame(1, \count($jsonData['items']));
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($followedUser->getId(), $jsonData['items'][0]['userId']);
    }

    public function testApiCanGetFollowersById(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followingUser = $this->getUserByUsername('JohnDoe');

        $followingUser->follow($testUser);

        $manager = $this->entityManager;

        $manager->persist($testUser);
        $manager->flush();

        $this->client->loginUser($followingUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('GET', '/api/users/'.(string) $testUser->getId().'/followers', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertSame(1, \count($jsonData['items']));
        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($followingUser->getId(), $jsonData['items'][0]['userId']);
    }

    public function testApiCannotGetSettingsWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('GET', '/api/users/settings', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanGetSettings(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('JohnDoe');

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:profile:read');

        $this->client->request('GET', '/api/users/settings', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::USER_SETTINGS_KEYS, $jsonData);
    }
}

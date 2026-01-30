<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class UserBlockApiTest extends WebTestCase
{
    public function testApiCannotBlockUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $blockedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('PUT', '/api/users/'.(string) $blockedUser->getId().'/block', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUnblockUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $blockedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('PUT', '/api/users/'.(string) $blockedUser->getId().'/unblock', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanBlockUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout');
        $followedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:follow user:block');

        $this->client->request('PUT', '/api/users/'.(string) $followedUser->getId().'/block', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('userId', $jsonData);
        self::assertArrayHasKey('username', $jsonData);
        self::assertArrayHasKey('about', $jsonData);
        self::assertArrayHasKey('avatar', $jsonData);
        self::assertArrayHasKey('cover', $jsonData);
        self::assertArrayNotHasKey('lastActive', $jsonData);
        self::assertArrayHasKey('createdAt', $jsonData);
        self::assertArrayHasKey('followersCount', $jsonData);
        self::assertArrayHasKey('apId', $jsonData);
        self::assertArrayHasKey('apProfileId', $jsonData);
        self::assertArrayHasKey('isBot', $jsonData);
        self::assertArrayHasKey('isFollowedByUser', $jsonData);
        self::assertArrayHasKey('isFollowerOfUser', $jsonData);
        self::assertArrayHasKey('isBlockedByUser', $jsonData);

        self::assertSame(0, $jsonData['followersCount']);
        self::assertFalse($jsonData['isFollowedByUser']);
        self::assertFalse($jsonData['isFollowerOfUser']);
        self::assertTrue($jsonData['isBlockedByUser']);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanUnblockUser(): void
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

        $this->client->request('PUT', '/api/users/'.(string) $blockedUser->getId().'/unblock', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('userId', $jsonData);
        self::assertArrayHasKey('username', $jsonData);
        self::assertArrayHasKey('about', $jsonData);
        self::assertArrayHasKey('avatar', $jsonData);
        self::assertArrayHasKey('cover', $jsonData);
        self::assertArrayNotHasKey('lastActive', $jsonData);
        self::assertArrayHasKey('createdAt', $jsonData);
        self::assertArrayHasKey('followersCount', $jsonData);
        self::assertArrayHasKey('apId', $jsonData);
        self::assertArrayHasKey('apProfileId', $jsonData);
        self::assertArrayHasKey('isBot', $jsonData);
        self::assertArrayHasKey('isFollowedByUser', $jsonData);
        self::assertArrayHasKey('isFollowerOfUser', $jsonData);
        self::assertArrayHasKey('isBlockedByUser', $jsonData);

        self::assertSame(0, $jsonData['followersCount']);
        self::assertFalse($jsonData['isFollowedByUser']);
        self::assertFalse($jsonData['isFollowerOfUser']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }
}

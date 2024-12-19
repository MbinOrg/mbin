<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User\Admin;

use App\Tests\WebTestCase;

class UserBanApiTest extends WebTestCase
{
    public function testApiCannotBanUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/ban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertFalse($bannedUser->isBanned);
    }

    public function testApiCannotUnbanUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->userManager->ban($bannedUser);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/unban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertTrue($bannedUser->isBanned);
    }

    public function testApiCannotBanUserWithoutAdminAccount(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: false);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/ban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertFalse($bannedUser->isBanned);
    }

    public function testApiCannotUnbanUserWithoutAdminAccount(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: false);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->userManager->ban($bannedUser);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/unban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertTrue($bannedUser->isBanned);
    }

    public function testApiCanBanUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/ban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(array_merge(self::USER_RESPONSE_KEYS, ['isBanned']), $jsonData);
        self::assertTrue($jsonData['isBanned']);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertTrue($bannedUser->isBanned);
    }

    public function testApiCanUnbanUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');

        $this->userManager->ban($bannedUser);

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/unban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(array_merge(self::USER_RESPONSE_KEYS, ['isBanned']), $jsonData);
        self::assertFalse($jsonData['isBanned']);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertFalse($bannedUser->isBanned);
    }

    public function testBanApiReturns404IfUserNotFound(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) ($bannedUser->getId() * 10).'/ban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(404);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertFalse($bannedUser->isBanned);
    }

    public function testUnbanApiReturns404IfUserNotFound(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');

        $this->userManager->ban($bannedUser);

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) ($bannedUser->getId() * 10).'/unban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(404);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertTrue($bannedUser->isBanned);
    }

    public function testBanApiReturns401IfTokenNotProvided(): void
    {
        $bannedUser = $this->getUserByUsername('JohnDoe');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/ban');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUnbanApiReturns401IfTokenNotProvided(): void
    {
        $bannedUser = $this->getUserByUsername('JohnDoe');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/unban');
        self::assertResponseStatusCodeSame(401);
    }

    public function testBanApiIsIdempotent(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');

        $this->userManager->ban($bannedUser);

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        // Ban user a second time with the API
        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/ban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(array_merge(self::USER_RESPONSE_KEYS, ['isBanned']), $jsonData);
        self::assertTrue($jsonData['isBanned']);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertTrue($bannedUser->isBanned);
    }

    public function testUnbanApiIsIdempotent(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $bannedUser = $this->getUserByUsername('JohnDoe');

        // Do not ban user

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:ban');

        $this->client->request('POST', '/api/admin/users/'.(string) $bannedUser->getId().'/unban', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(array_merge(self::USER_RESPONSE_KEYS, ['isBanned']), $jsonData);
        self::assertFalse($jsonData['isBanned']);

        $repository = $this->userRepository;
        $bannedUser = $repository->find($bannedUser->getId());
        self::assertFalse($bannedUser->isBanned);
    }
}

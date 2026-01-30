<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User\Admin;

use App\Tests\WebTestCase;

class UserVerifyApiTest extends WebTestCase
{
    public function testApiCannotVerifyUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('PUT', '/api/admin/users/'.(string) $unverifiedUser->getId().'/verify', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $unverifiedUser = $repository->find($unverifiedUser->getId());
        self::assertFalse($unverifiedUser->isVerified);
    }

    public function testApiCannotVerifyUserWithoutAdminAccount(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: false);
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:verify');

        $this->client->request('PUT', '/api/admin/users/'.(string) $unverifiedUser->getId().'/verify', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $unverifiedUser = $repository->find($unverifiedUser->getId());
        self::assertFalse($unverifiedUser->isVerified);
    }

    public function testApiCanVerifyUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:verify');

        $this->client->request('PUT', '/api/admin/users/'.(string) $unverifiedUser->getId().'/verify', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(200);

        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(array_merge(self::USER_RESPONSE_KEYS, ['isVerified']), $jsonData);
        self::assertTrue($jsonData['isVerified']);

        $repository = $this->userRepository;
        $unverifiedUser = $repository->find($unverifiedUser->getId());
        self::assertTrue($unverifiedUser->isVerified);
    }

    public function testVerifyApiReturns404IfUserNotFound(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false);
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:verify');

        $this->client->request('PUT', '/api/admin/users/'.(string) ($unverifiedUser->getId() * 10).'/verify', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testVerifyApiReturns401IfTokenNotProvided(): void
    {
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false);

        $this->client->request('PUT', '/api/admin/users/'.(string) $unverifiedUser->getId().'/verify');
        self::assertResponseStatusCodeSame(401);
    }
}

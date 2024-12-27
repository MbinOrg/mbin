<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User\Admin;

use App\Tests\WebTestCase;

class UserPurgeApiTest extends WebTestCase
{
    public function testApiCannotPurgeUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $purgedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request('DELETE', '/api/admin/users/'.(string) $purgedUser->getId().'/purge_account', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $purgedUser = $repository->find($purgedUser->getId());
        self::assertNotNull($purgedUser);
    }

    public function testApiCannotPurgeUserWithoutAdminAccount(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: false);
        $purgedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:purge');

        $this->client->request('DELETE', '/api/admin/users/'.(string) $purgedUser->getId().'/purge_account', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);

        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $purgedUser = $repository->find($purgedUser->getId());
        self::assertNotNull($purgedUser);
    }

    public function testApiCanPurgeUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $purgedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:purge');

        $this->client->request('DELETE', '/api/admin/users/'.(string) $purgedUser->getId().'/purge_account', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(204);

        $repository = $this->userRepository;
        $purgedUser = $repository->find($purgedUser->getId());
        self::assertNull($purgedUser);
    }

    public function testPurgeApiReturns404IfUserNotFound(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $purgedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:purge');

        $this->client->request('DELETE', '/api/admin/users/'.(string) ($purgedUser->getId() * 10).'/purge_account', server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]);
        self::assertResponseStatusCodeSame(404);

        $repository = $this->userRepository;
        $purgedUser = $repository->find($purgedUser->getId());
        self::assertNotNull($purgedUser);
    }

    public function testPurgeApiReturns401IfTokenNotProvided(): void
    {
        $purgedUser = $this->getUserByUsername('JohnDoe');

        $this->client->request('DELETE', '/api/admin/users/'.(string) $purgedUser->getId().'/purge_account');
        self::assertResponseStatusCodeSame(401);
    }
}

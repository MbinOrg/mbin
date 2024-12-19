<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User\Admin;

use App\Tests\WebTestCase;

class UserDeleteApiTest extends WebTestCase
{
    public function testApiCannotDeleteUserWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $deletedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');

        $this->client->request(
            'DELETE',
            '/api/admin/users/'.(string) $deletedUser->getId().'/delete_account',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $deletedUser = $repository->find($deletedUser->getId());
        self::assertFalse($deletedUser->isAccountDeleted());
    }

    public function testApiCannotDeleteUserWithoutAdminAccount(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: false);
        $deletedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:delete');

        $this->client->request(
            'DELETE',
            '/api/admin/users/'.(string) $deletedUser->getId().'/delete_account',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(403);

        $repository = $this->userRepository;
        $deletedUser = $repository->find($deletedUser->getId());
        self::assertFalse($deletedUser->isAccountDeleted());
    }

    public function testApiCanDeleteUser(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $deletedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:delete');

        $this->client->request(
            'DELETE',
            '/api/admin/users/'.(string) $deletedUser->getId().'/delete_account',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::USER_RESPONSE_KEYS, $jsonData);

        $repository = $this->userRepository;
        $deletedUser = $repository->find($deletedUser->getId());
        self::assertTrue($deletedUser->isAccountDeleted());
    }

    public function testDeleteApiReturns404IfUserNotFound(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $deletedUser = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:delete');

        $this->client->request(
            'DELETE',
            '/api/admin/users/'.(string) ($deletedUser->getId() * 10).'/delete_account',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(404);

        $repository = $this->userRepository;
        $deletedUser = $repository->find($deletedUser->getId());
        self::assertFalse($deletedUser->isBanned);
    }

    public function testDeleteApiReturns401IfTokenNotProvided(): void
    {
        $deletedUser = $this->getUserByUsername('JohnDoe');

        $this->client->request('DELETE', '/api/admin/users/'.(string) $deletedUser->getId().'/delete_account');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteApiIsNotIdempotent(): void
    {
        self::createOAuth2AuthCodeClient();
        $testUser = $this->getUserByUsername('UserWithoutAbout', isAdmin: true);
        $deletedUser = $this->getUserByUsername('JohnDoe');
        $deleteId = $deletedUser->getId();
        $this->userManager->delete($deletedUser);

        $this->client->loginUser($testUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:user:delete');

        // Ban user a second time with the API
        $this->client->request(
            'DELETE',
            '/api/admin/users/'.(string) $deleteId.'/delete_account',
            server: ['HTTP_AUTHORIZATION' => $codes['token_type'].' '.$codes['access_token']]
        );
        self::assertResponseStatusCodeSame(404);

        $repository = $this->userRepository;
        $deletedUser = $repository->find($deleteId);
        self::assertNull($deletedUser);
    }
}

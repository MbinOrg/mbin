<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Enums\EApplicationStatus;
use App\Tests\WebTestCase;

class ApiAccessTest extends WebTestCase
{
    public function testUnverifiedUserCannotAccessApiWithToken(): void
    {
        $unverifiedUser = $this->getUserByUsername('JohnDoe', active: false, addImage: false);
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($unverifiedUser);
        $unverifiedTokenData = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write user');
        $unverifiedToken = $unverifiedTokenData['token_type'].' '.$unverifiedTokenData['access_token'];

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $unverifiedToken]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithApIdCannotAccessApi(): void
    {
        $user = $this->getUserByUsername('JohnDoe', addImage: false);
        $user->apId = 'https://example.com/users/johndoe';

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $tokenData = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write user');
        $token = $tokenData['token_type'].' '.$tokenData['access_token'];

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithPendingApplicationCannotAccessApi(): void
    {
        $user = $this->getUserByUsername('JohnDoe', addImage: false);
        $user->setApplicationStatus(EApplicationStatus::Pending);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $tokenData = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write user');
        $token = $tokenData['token_type'].' '.$tokenData['access_token'];

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithRejectedApplicationCannotAccessApi(): void
    {
        $user = $this->getUserByUsername('JohnDoe', addImage: false);
        $user->setApplicationStatus(EApplicationStatus::Rejected);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $tokenData = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write user');
        $token = $tokenData['token_type'].' '.$tokenData['access_token'];

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testBannedUserCannotAccessApi(): void
    {
        $user = $this->getUserByUsername('JohnDoe', addImage: false);
        $user->isBanned = true;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);
        $tokenData = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write user');
        $token = $tokenData['token_type'].' '.$tokenData['access_token'];

        $this->client->request('GET', '/api/users/me', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(401);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance\Admin;

use App\Tests\WebTestCase;

class InstanceFederationUpdateApiTest extends WebTestCase
{
    public function testApiCannotUpdateInstanceFederationAnonymous(): void
    {
        $this->client->request('PUT', '/api/defederated');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceFederationWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/defederated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceFederationWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/defederated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceFederation(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:federation:update');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/defederated', ['instances' => ['bad-instance.com']], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(['instances'], $jsonData);
        self::assertSame(['bad-instance.com'], $jsonData['instances']);
    }

    public function testApiCanClearInstanceFederation(): void
    {
        $manager = $this->settingsManager;
        $manager->set('KBIN_BANNED_INSTANCES', ['defederated.social', 'evil.social']);

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:federation:update');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/defederated', ['instances' => []], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(['instances'], $jsonData);
        self::assertEmpty($jsonData['instances']);
    }
}

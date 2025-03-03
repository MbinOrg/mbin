<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class InstanceFederationApiTest extends WebTestCase
{
    public const INSTANCE_DEFEDERATED_RESPONSE_KEYS = ['instances'];

    public function testApiCanRetrieveEmptyInstanceDefederation(): void
    {
        $settings = $this->settingsManager;
        $settings->set('KBIN_BANNED_INSTANCES', []);

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/defederated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertSame([], $jsonData['instances']);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanRetrieveInstanceDefederationAnonymous(): void
    {
        $settings = $this->settingsManager;
        $settings->set('KBIN_BANNED_INSTANCES', ['defederated.social']);

        $this->client->request('GET', '/api/defederated');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertSame(['defederated.social'], $jsonData['instances']);
    }

    #[Group(name: 'NonThreadSafe')]
    public function testApiCanRetrieveInstanceDefederation(): void
    {
        $settings = $this->settingsManager;
        $settings->set('KBIN_BANNED_INSTANCES', ['defederated.social', 'evil.social']);

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/defederated', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_DEFEDERATED_RESPONSE_KEYS, $jsonData);
        self::assertSame(['defederated.social', 'evil.social'], $jsonData['instances']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance\Admin;

use App\Tests\WebTestCase;

class InstanceSettingsRetrieveApiTest extends WebTestCase
{
    public const INSTANCE_SETTINGS_RESPONSE_KEYS = [
        'KBIN_DOMAIN',
        'KBIN_TITLE',
        'KBIN_META_TITLE',
        'KBIN_META_KEYWORDS',
        'KBIN_META_DESCRIPTION',
        'KBIN_DEFAULT_LANG',
        'KBIN_CONTACT_EMAIL',
        'KBIN_SENDER_EMAIL',
        'MBIN_DEFAULT_THEME',
        'KBIN_JS_ENABLED',
        'KBIN_FEDERATION_ENABLED',
        'KBIN_REGISTRATIONS_ENABLED',
        'KBIN_BANNED_INSTANCES',
        'KBIN_HEADER_LOGO',
        'KBIN_CAPTCHA_ENABLED',
        'KBIN_MERCURE_ENABLED',
        'KBIN_FEDERATION_PAGE_ENABLED',
        'KBIN_ADMIN_ONLY_OAUTH_CLIENTS',
        'MBIN_PRIVATE_INSTANCE',
        'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN',
        'MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY',
        'MBIN_SSO_REGISTRATIONS_ENABLED',
        'MBIN_RESTRICT_MAGAZINE_CREATION',
        'MBIN_DOWNVOTES_MODE',
        'MBIN_SSO_ONLY_MODE',
        'MBIN_SSO_SHOW_FIRST',
        'MAX_IMAGE_BYTES',
        'MBIN_NEW_USERS_NEED_APPROVAL',
    ];

    public function testApiCannotRetrieveInstanceSettingsAnonymous(): void
    {
        $this->client->request('GET', '/api/instance/settings');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveInstanceSettingsWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/instance/settings', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRetrieveInstanceSettingsWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/instance/settings', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveInstanceSettings(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:settings:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/instance/settings', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_SETTINGS_RESPONSE_KEYS, $jsonData);
        foreach ($jsonData as $key => $value) {
            self::assertNotNull($value, "$key was null!");
        }
    }
}

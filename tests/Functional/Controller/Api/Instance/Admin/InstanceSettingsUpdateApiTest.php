<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance\Admin;

use App\Service\SettingsManager;
use App\Tests\WebTestCase;
use App\Utils\DownvotesMode;

class InstanceSettingsUpdateApiTest extends WebTestCase
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
        'KBIN_HEADER_LOGO',
        'KBIN_CAPTCHA_ENABLED',
        'KBIN_MERCURE_ENABLED',
        'KBIN_FEDERATION_PAGE_ENABLED',
        'KBIN_ADMIN_ONLY_OAUTH_CLIENTS',
        'MBIN_PRIVATE_INSTANCE',
        'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN',
        'MBIN_SIDEBAR_SECTIONS_RANDOM_LOCAL_ONLY',
        'MBIN_SIDEBAR_SECTIONS_USERS_LOCAL_ONLY',
        'MBIN_SSO_REGISTRATIONS_ENABLED',
        'MBIN_RESTRICT_MAGAZINE_CREATION',
        'MBIN_DOWNVOTES_MODE',
        'MBIN_SSO_ONLY_MODE',
        'MBIN_SSO_SHOW_FIRST',
        'MBIN_MAX_IMAGE_BYTES',
        'MBIN_NEW_USERS_NEED_APPROVAL',
        'MBIN_USE_FEDERATION_ALLOW_LIST',
    ];

    public function testApiCannotUpdateInstanceSettingsAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/settings');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceSettingsWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/settings', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceSettingsWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/settings', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceSettings(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:settings:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $settings = [
            'KBIN_DOMAIN' => 'kbinupdated.test',
            'KBIN_TITLE' => 'updated title',
            'KBIN_META_TITLE' => 'meta title',
            'KBIN_META_KEYWORDS' => 'this, is, a, test',
            'KBIN_META_DESCRIPTION' => 'Testing out the API',
            'KBIN_DEFAULT_LANG' => 'de',
            'KBIN_CONTACT_EMAIL' => 'test@kbinupdated.test',
            'KBIN_SENDER_EMAIL' => 'noreply@kbinupdated.test',
            'MBIN_DEFAULT_THEME' => 'dark',
            'KBIN_JS_ENABLED' => true,
            'KBIN_FEDERATION_ENABLED' => true,
            'KBIN_REGISTRATIONS_ENABLED' => false,
            'KBIN_HEADER_LOGO' => true,
            'KBIN_CAPTCHA_ENABLED' => true,
            'KBIN_MERCURE_ENABLED' => false,
            'KBIN_FEDERATION_PAGE_ENABLED' => false,
            'KBIN_ADMIN_ONLY_OAUTH_CLIENTS' => true,
            'MBIN_PRIVATE_INSTANCE' => true,
            'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN' => false,
            'MBIN_SIDEBAR_SECTIONS_RANDOM_LOCAL_ONLY' => false,
            'MBIN_SIDEBAR_SECTIONS_USERS_LOCAL_ONLY' => false,
            'MBIN_SSO_REGISTRATIONS_ENABLED' => true,
            'MBIN_RESTRICT_MAGAZINE_CREATION' => false,
            'MBIN_DOWNVOTES_MODE' => DownvotesMode::Enabled->value,
            'MBIN_SSO_ONLY_MODE' => false,
            'MBIN_SSO_SHOW_FIRST' => false,
            'MBIN_MAX_IMAGE_BYTES' => 10000,
            'MBIN_NEW_USERS_NEED_APPROVAL' => false,
            'MBIN_USE_FEDERATION_ALLOW_LIST' => false,
        ];

        $this->client->jsonRequest('PUT', '/api/instance/settings', $settings, server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_SETTINGS_RESPONSE_KEYS, $jsonData);
        foreach ($jsonData as $key => $value) {
            self::assertEquals($settings[$key], $value, "$key did not match!");
        }

        $settings = [
            'KBIN_DOMAIN' => 'kbin.test',
            'KBIN_TITLE' => 'updated title',
            'KBIN_META_TITLE' => 'meta title',
            'KBIN_META_KEYWORDS' => 'this, is, a, test',
            'KBIN_META_DESCRIPTION' => 'Testing out the API',
            'KBIN_DEFAULT_LANG' => 'en',
            'KBIN_CONTACT_EMAIL' => 'test@kbinupdated.test',
            'KBIN_SENDER_EMAIL' => 'noreply@kbinupdated.test',
            'MBIN_DEFAULT_THEME' => 'light',
            'KBIN_JS_ENABLED' => false,
            'KBIN_FEDERATION_ENABLED' => false,
            'KBIN_REGISTRATIONS_ENABLED' => true,
            'KBIN_HEADER_LOGO' => false,
            'KBIN_CAPTCHA_ENABLED' => false,
            'KBIN_MERCURE_ENABLED' => true,
            'KBIN_FEDERATION_PAGE_ENABLED' => true,
            'KBIN_ADMIN_ONLY_OAUTH_CLIENTS' => false,
            'MBIN_PRIVATE_INSTANCE' => false,
            'KBIN_FEDERATED_SEARCH_ONLY_LOGGEDIN' => true,
            'MBIN_SIDEBAR_SECTIONS_RANDOM_LOCAL_ONLY' => true,
            'MBIN_SIDEBAR_SECTIONS_USERS_LOCAL_ONLY' => true,
            'MBIN_SSO_REGISTRATIONS_ENABLED' => false,
            'MBIN_RESTRICT_MAGAZINE_CREATION' => true,
            'MBIN_DOWNVOTES_MODE' => DownvotesMode::Hidden->value,
            'MBIN_SSO_ONLY_MODE' => true,
            'MBIN_SSO_SHOW_FIRST' => true,
            'MBIN_MAX_IMAGE_BYTES' => 30000,
            'MBIN_NEW_USERS_NEED_APPROVAL' => false,
            'MBIN_USE_FEDERATION_ALLOW_LIST' => false,
        ];

        $this->client->jsonRequest('PUT', '/api/instance/settings', $settings, server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(self::INSTANCE_SETTINGS_RESPONSE_KEYS, $jsonData);
        foreach ($jsonData as $key => $value) {
            self::assertEquals($settings[$key], $value, "$key did not match!");
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        SettingsManager::resetDto();
    }
}

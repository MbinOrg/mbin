<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Instance\Admin;

use App\Tests\Functional\Controller\Api\Instance\InstanceDetailsApiTest;
use App\Tests\WebTestCase;

class InstancePagesUpdateApiTest extends WebTestCase
{
    public function testApiCannotUpdateInstanceAboutPageAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/about');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceAboutPageWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/about', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceAboutPageWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/about', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceAboutPage(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:information:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/instance/about', ['body' => 'about page'], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceDetailsApiTest::INSTANCE_PAGE_RESPONSE_KEYS, $jsonData);
        self::assertEquals('about page', $jsonData['about']);
    }

    public function testApiCannotUpdateInstanceContactPageAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/contact');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceContactPageWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/contact', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceContactPageWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/contact', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceContactPage(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:information:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/instance/contact', ['body' => 'contact page'], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceDetailsApiTest::INSTANCE_PAGE_RESPONSE_KEYS, $jsonData);
        self::assertEquals('contact page', $jsonData['contact']);
    }

    public function testApiCannotUpdateInstanceFAQPageAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/faq');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceFAQPageWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/faq', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceFAQPageWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/faq', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceFAQPage(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:information:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/instance/faq', ['body' => 'faq page'], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceDetailsApiTest::INSTANCE_PAGE_RESPONSE_KEYS, $jsonData);
        self::assertEquals('faq page', $jsonData['faq']);
    }

    public function testApiCannotUpdateInstancePrivacyPolicyPageAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/privacyPolicy');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstancePrivacyPolicyPageWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/privacyPolicy', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstancePrivacyPolicyPageWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/privacyPolicy', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstancePrivacyPolicyPage(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:information:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/instance/privacyPolicy', ['body' => 'privacyPolicy page'], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceDetailsApiTest::INSTANCE_PAGE_RESPONSE_KEYS, $jsonData);
        self::assertEquals('privacyPolicy page', $jsonData['privacyPolicy']);
    }

    public function testApiCannotUpdateInstanceTermsPageAnonymous(): void
    {
        $this->client->request('PUT', '/api/instance/terms');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateInstanceTermsPageWithoutAdmin(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/terms', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotUpdateInstanceTermsPageWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/instance/terms', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateInstanceTermsPage(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:instance:information:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', '/api/instance/terms', ['body' => 'terms page'], server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertArrayKeysMatch(InstanceDetailsApiTest::INSTANCE_PAGE_RESPONSE_KEYS, $jsonData);
        self::assertEquals('terms page', $jsonData['terms']);
    }
}

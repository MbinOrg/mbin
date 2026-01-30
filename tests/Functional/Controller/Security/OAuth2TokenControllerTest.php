<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Security;

use App\Tests\WebTestCase;

class OAuth2TokenControllerTest extends WebTestCase
{
    public function testCanGetTokenWithValidClientCredentials(): void
    {
        self::createOAuth2ClientCredsClient();

        $this->client->request('POST', '/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'testclient',
            'client_secret' => 'testsecret',
            'scope' => 'read write',
        ]);

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('token_type', $jsonData);
        self::assertEquals('Bearer', $jsonData['token_type']);
        self::assertArrayHasKey('expires_in', $jsonData);
        self::assertIsInt($jsonData['expires_in']);
        self::assertArrayHasKey('access_token', $jsonData);
        self::assertMatchesRegularExpression(self::JWT_REGEX, $jsonData['access_token']);
        self::assertArrayNotHasKey('refresh_token', $jsonData);
    }

    public function testCanGetTokenWithValidAuthorizationCode(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $jsonData = self::getAuthorizationCodeTokenResponse($this->client);

        self::assertResponseIsSuccessful();
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('token_type', $jsonData);
        self::assertEquals('Bearer', $jsonData['token_type']);
        self::assertArrayHasKey('expires_in', $jsonData);
        self::assertIsInt($jsonData['expires_in']);
        self::assertArrayHasKey('access_token', $jsonData);
        self::assertMatchesRegularExpression(self::JWT_REGEX, $jsonData['access_token']);
        self::assertArrayHasKey('refresh_token', $jsonData);
        self::assertMatchesRegularExpression(self::CODE_REGEX, $jsonData['refresh_token']);
    }

    public function testCanGetTokenWithValidRefreshToken(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $jsonData = self::getAuthorizationCodeTokenResponse($this->client);

        self::assertResponseIsSuccessful();
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('refresh_token', $jsonData);

        $jsonData = self::getRefreshTokenResponse($this->client, $jsonData['refresh_token']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('token_type', $jsonData);
        self::assertEquals('Bearer', $jsonData['token_type']);
        self::assertArrayHasKey('expires_in', $jsonData);
        self::assertIsInt($jsonData['expires_in']);
        self::assertArrayHasKey('access_token', $jsonData);
        self::assertMatchesRegularExpression(self::JWT_REGEX, $jsonData['access_token']);
        self::assertArrayHasKey('refresh_token', $jsonData);
        self::assertMatchesRegularExpression(self::CODE_REGEX, $jsonData['refresh_token']);
    }

    public function testCanGetTokenWithValidAuthorizationCodePKCE(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2PublicAuthCodeClient();

        $jsonData = self::getPublicAuthorizationCodeTokenResponse($this->client);

        self::assertResponseIsSuccessful();
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('token_type', $jsonData);
        self::assertEquals('Bearer', $jsonData['token_type']);
        self::assertArrayHasKey('expires_in', $jsonData);
        self::assertIsInt($jsonData['expires_in']);
        self::assertArrayHasKey('access_token', $jsonData);
        self::assertMatchesRegularExpression(self::JWT_REGEX, $jsonData['access_token']);
        self::assertArrayHasKey('refresh_token', $jsonData);
        self::assertMatchesRegularExpression(self::CODE_REGEX, $jsonData['refresh_token']);
    }

    public function testCannotGetTokenWithInvalidVerifierAuthorizationCodePKCE(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2PublicAuthCodeClient();

        $pkceCodes = self::runPublicAuthorizationCodeFlow($this->client, 'yes');
        self::runPublicAuthorizationCodeTokenFetch($this->client, $pkceCodes['verifier'].'fail');

        $jsonData = self::getJsonResponse($this->client);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_grant', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
        self::assertArrayHasKey('hint', $jsonData);
    }

    public function testCannotGetTokenWithoutChallengeAuthorizationCodePKCE(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2PublicAuthCodeClient();

        $query = self::buildPrivateAuthCodeQuery('testpublicclient', 'read write', 'oauth2state', 'https://localhost:3001');

        $uri = '/authorize?'.$query;

        $this->client->request('GET', $uri);

        $jsonData = self::getJsonResponse($this->client);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_request', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
        self::assertArrayHasKey('hint', $jsonData);
        self::assertStringContainsStringIgnoringCase('code challenge', $jsonData['hint']);
    }

    public function testReceiveErrorWithInvalidAuthorizationCode(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $this->client->request('POST', '/token', [
            'grant_type' => 'authorization_code',
            'client_id' => 'testclient',
            'client_secret' => 'testsecret',
            'code' => 'deadbeefc0de',
            'redirect_uri' => 'https://localhost:3001',
        ]);

        self::assertResponseStatusCodeSame(400);

        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_grant', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
        self::assertArrayHasKey('hint', $jsonData);
    }

    public function testReceiveErrorWithInvalidClientId(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $query = self::buildPrivateAuthCodeQuery('testclientfake', 'read write', 'oauth2state', 'https://localhost:3001');

        $uri = '/authorize?'.$query;

        $this->client->request('GET', $uri);

        $jsonData = self::getJsonResponse($this->client);

        self::assertResponseStatusCodeSame(401);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_client', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
    }

    public function testReceiveErrorWithInvalidClientSecret(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $jsonData = self::getAuthorizationCodeTokenResponse($this->client, clientSecret: 'testsecretfake');

        self::assertResponseStatusCodeSame(401);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_client', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
    }

    public function testReceiveErrorWithInvalidRedirectUri(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $query = self::buildPrivateAuthCodeQuery('testclient', 'read write', 'oauth2state', 'https://invalid.com');

        $uri = '/authorize?'.$query;

        $this->client->request('GET', $uri);

        $jsonData = self::getJsonResponse($this->client);

        self::assertResponseStatusCodeSame(401);

        self::assertIsArray($jsonData);
        self::assertArrayHasKey('error', $jsonData);
        self::assertEquals('invalid_client', $jsonData['error']);
        self::assertArrayHasKey('error_description', $jsonData);
    }
}

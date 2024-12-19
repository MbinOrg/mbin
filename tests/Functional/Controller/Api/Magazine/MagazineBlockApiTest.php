<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\Tests\WebTestCase;

class MagazineBlockApiTest extends WebTestCase
{
    public function testApiCannotBlockMagazineAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/block');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotBlockMagazineWithoutScope(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/block', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanBlockMagazine(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/block', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertTrue($jsonData['isBlockedByUser']);

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId(), server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertTrue($jsonData['isBlockedByUser']);
    }

    public function testApiCannotUnblockMagazineAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unblock');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnblockMagazineWithoutScope(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unblock', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnblockMagazine(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $manager = $this->magazineManager;
        $manager->block($magazine, $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unblock', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId(), server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertNull($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }
}

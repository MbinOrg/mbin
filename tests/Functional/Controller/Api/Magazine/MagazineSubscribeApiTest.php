<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\Tests\WebTestCase;

class MagazineSubscribeApiTest extends WebTestCase
{
    public function testApiCannotSubscribeToMagazineAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/subscribe');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotSubscribeToMagazineWithoutScope(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSubscribeToMagazine(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/subscribe', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertTrue($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId(), server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertTrue($jsonData['isUserSubscribed']);
        self::assertFalse($jsonData['isBlockedByUser']);
    }

    public function testApiCannotUnsubscribeFromMagazineAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unsubscribe');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUnsubscribeFromMagazineWithoutScope(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:block');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unsubscribe', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnsubscribeFromMagazine(): void
    {
        $user = $this->getUserByUsername('testuser');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $manager = $this->magazineManager;
        $manager->subscribe($magazine, $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/magazine/'.(string) $magazine->getId().'/unsubscribe', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertFalse($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId(), server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);

        // Scopes for reading subscriptions and blocklists granted, so these values should be filled
        self::assertFalse($jsonData['isUserSubscribed']);
        self::assertNull($jsonData['isBlockedByUser']);
    }
}

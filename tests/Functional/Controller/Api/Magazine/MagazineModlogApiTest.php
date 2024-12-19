<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\Tests\WebTestCase;

class MagazineModlogApiTest extends WebTestCase
{
    public function testApiCanRetrieveModlogByMagazineIdAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId().'/log');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
    }

    public function testApiCanRetrieveMagazineById(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId().'/log', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertEmpty($jsonData['items']);
    }

    public function testApiModlogReflectsModerationActionsTaken(): void
    {
        $this->createModlogMessages();
        $magazine = $this->getMagazineByName('acme');
        $moderator = $magazine->getOwner();

        $entityManager = $this->entityManager;
        $entityManager->refresh($magazine);

        $this->client->request('GET', '/api/magazine/'.(string) $magazine->getId().'/log');

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(5, $jsonData['items']);

        $this->validateModlog($jsonData, $magazine, $moderator);
    }
}

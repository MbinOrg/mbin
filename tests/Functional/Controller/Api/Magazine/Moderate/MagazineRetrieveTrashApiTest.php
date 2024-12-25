<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Moderate;

use App\Entity\Contracts\VisibilityInterface;
use App\Tests\Functional\Controller\Api\Magazine\MagazineRetrieveApiTest;
use App\Tests\WebTestCase;

class MagazineRetrieveTrashApiTest extends WebTestCase
{
    public function testApiCannotRetrieveMagazineTrashAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->client->request('GET', "/api/moderate/magazine/{$magazine->getId()}/trash");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRetrieveMagazineTrashWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/moderate/magazine/{$magazine->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRetrieveMagazineTrashIfNotMod(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:trash:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));
        $this->client->request('GET', "/api/moderate/magazine/{$magazine->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRetrieveMagazineTrash(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');

        $reportedUser = $this->getUserByUsername('hapless_fool');
        $entry = $this->getEntryByTitle('Delete test', body: 'This is gonna be deleted', magazine: $magazine, user: $reportedUser);

        $entryManager = $this->entryManager;
        $entryManager->delete($user, $entry);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:trash:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/moderate/magazine/{$magazine->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);

        $trashedEntryResponseKeys = array_merge(self::ENTRY_RESPONSE_KEYS, ['itemType']);

        self::assertArrayKeysMatch($trashedEntryResponseKeys, $jsonData['items'][0]);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame($entry->getId(), $jsonData['items'][0]['entryId']);
        self::assertEquals($entry->body, $jsonData['items'][0]['body']);
        self::assertEquals(VisibilityInterface::VISIBILITY_TRASHED, $jsonData['items'][0]['visibility']);
    }
}

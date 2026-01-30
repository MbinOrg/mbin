<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Admin;

use App\Tests\WebTestCase;

class EntryChangeMagazineApiTest extends WebTestCase
{
    public function testApiCannotChangeEntryMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $magazine2 = $this->getMagazineByNameNoRSAKey('acme2');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', magazine: $magazine);

        $this->client->jsonRequest('PUT', "/api/admin/entry/{$entry->getId()}/change-magazine/{$magazine2->getId()}");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiNonAdminCannotChangeEntryMagazine(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $magazine2 = $this->getMagazineByNameNoRSAKey('acme2');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:magazine:move_entry');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/admin/entry/{$entry->getId()}/change-magazine/{$magazine2->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotChangeEntryMagazineWithoutScope(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $magazine2 = $this->getMagazineByNameNoRSAKey('acme2');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/admin/entry/{$entry->getId()}/change-magazine/{$magazine2->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanChangeEntryMagazine(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $magazine2 = $this->getMagazineByNameNoRSAKey('acme2');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:magazine:move_entry');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/admin/entry/{$entry->getId()}/change-magazine/{$magazine2->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData);
        self::assertSame($entry->getId(), $jsonData['entryId']);
        self::assertEquals($entry->title, $jsonData['title']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine2->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertNull($jsonData['domain']);
        self::assertNull($jsonData['url']);
        self::assertEquals($entry->body, $jsonData['body']);
        self::assertNull($jsonData['image']);
        self::assertEquals($entry->lang, $jsonData['lang']);
        self::assertEmpty($jsonData['tags']);
        self::assertIsArray($jsonData['badges']);
        self::assertEmpty($jsonData['badges']);
        self::assertSame(0, $jsonData['numComments']);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        // No scope for seeing votes granted
        self::assertNull($jsonData['isFavourited']);
        self::assertNull($jsonData['userVote']);
        self::assertFalse($jsonData['isOc']);
        self::assertFalse($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertEquals('article', $jsonData['type']);
        self::assertEquals('test-article', $jsonData['slug']);
        self::assertNull($jsonData['apId']);
    }
}

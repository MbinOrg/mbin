<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Moderate;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class EntrySetAdultApiTest extends WebTestCase
{
    public function testApiCannotSetEntryAdultAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', magazine: $magazine);

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/true");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiNonModeratorCannotSetEntryAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotSetEntryAdultWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme', $user);
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSetEntryAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/true", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData);
        self::assertSame($entry->getId(), $jsonData['entryId']);
        self::assertEquals($entry->title, $jsonData['title']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
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
        self::assertTrue($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertEquals('visible', $jsonData['visibility']);
        self::assertEquals('article', $jsonData['type']);
        self::assertEquals('test-article', $jsonData['slug']);
        self::assertNull($jsonData['apId']);
    }

    public function testApiCannotSetEntryNotAdultAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', magazine: $magazine);

        $entityManager = $this->entityManager;
        $entry->isAdult = true;
        $entityManager->persist($entry);
        $entityManager->flush();

        $this->client->request('PUT', "/api/moderate/entry/{$entry->getId()}/adult/false");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiNonModeratorCannotSetEntryNotAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        $entityManager = $this->entityManager;
        $entry->isAdult = true;
        $entityManager->persist($entry);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotSetEntryNotAdultWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme', $user);
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        $entityManager = $this->entityManager;
        $entry->isAdult = true;
        $entityManager->persist($entry);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSetEntryNotAdult(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for favourite', user: $user, magazine: $magazine);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $entityManager = $this->entityManager;
        $entry->isAdult = true;
        $entityManager->persist($entry);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:entry:set_adult');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/entry/{$entry->getId()}/adult/false", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData);
        self::assertSame($entry->getId(), $jsonData['entryId']);
        self::assertEquals($entry->title, $jsonData['title']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
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
        self::assertEquals('visible', $jsonData['visibility']);
        self::assertEquals('article', $jsonData['type']);
        self::assertEquals('test-article', $jsonData['slug']);
        self::assertNull($jsonData['apId']);
    }
}

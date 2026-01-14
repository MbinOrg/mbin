<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine;

use App\DTO\MagazineBanDto;
use App\DTO\ModeratorDto;
use App\Entity\MagazineLog;
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

    public function testApiCanRetrieveModlogByMagazineIdAnonymouslyWithTypeFilter(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('GET', '/api/magazine/'.$magazine->getId().'/log?types[]='.MagazineLog::CHOICES[0].'&types[]='.MagazineLog::CHOICES[1]);

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

    public function testApiCanRetrieveEntryPinnedLog(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $entry = $this->getEntryByTitle('Something to pin', magazine: $magazine);
        $this->entryManager->pin($entry, $user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazine/'.$magazine->getId().'/log', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $item = $jsonData['items'][0];
        self::assertArrayKeysMatch(WebTestCase::LOG_ENTRY_KEYS, $item);
        self::assertEquals('log_entry_pinned', $item['type']);
        self::assertIsArray($item['subject']);
        self::assertArrayKeysMatch(WebTestCase::ENTRY_RESPONSE_KEYS, $item['subject']);
        self::assertEquals($entry->getId(), $item['subject']['entryId']);
    }

    public function testApiCanRetrieveUserBannedLog(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $banned = $this->getUserByUsername('troll');
        $dto = new MagazineBanDto();
        $dto->reason = 'because';
        $ban = $this->magazineManager->ban($magazine, $banned, $user, $dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazine/'.$magazine->getId().'/log', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $item = $jsonData['items'][0];
        self::assertArrayKeysMatch(WebTestCase::LOG_ENTRY_KEYS, $item);
        self::assertEquals('log_ban', $item['type']);
        self::assertArrayKeysMatch(WebTestCase::BAN_RESPONSE_KEYS, $item['subject']);
        self::assertEquals($ban->getId(), $item['subject']['banId']);
    }

    public function testApiCanRetrieveModeratorAddedLog(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $mod = $this->getUserByUsername('mod');
        $dto = new ModeratorDto($magazine, $mod, $user);
        $this->magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/magazine/'.$magazine->getId().'/log', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);
        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        $item = $jsonData['items'][0];
        self::assertArrayKeysMatch(WebTestCase::LOG_ENTRY_KEYS, $item);
        self::assertEquals('log_moderator_add', $item['type']);
        self::assertArrayKeysMatch(WebTestCase::USER_SMALL_RESPONSE_KEYS, $item['subject']);
        self::assertEquals($mod->getId(), $item['subject']['userId']);
        self::assertArrayKeysMatch(WebTestCase::USER_SMALL_RESPONSE_KEYS, $item['moderator']);
        self::assertEquals($user->getId(), $item['moderator']['userId']);
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

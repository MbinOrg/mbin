<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Admin;

use App\DTO\ModeratorDto;
use App\Tests\Functional\Controller\Api\Magazine\MagazineRetrieveApiTest;
use App\Tests\WebTestCase;

class MagazineModeratorsApiTest extends WebTestCase
{
    public function testApiCannotAddModeratorsToMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('notamod');
        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRemoveModeratorsFromMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('yesamod');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $user;
        $dto->addedBy = $admin;
        $magazineManager->addModerator($dto);

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotAddModeratorsToMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('notamod');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRemoveModeratorsFromMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('yesamod');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $user;
        $dto->addedBy = $admin;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiModCannotAddModeratorsMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $user = $this->getUserByUsername('notamod');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:moderators');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiModCannotRemoveModeratorsMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $user = $this->getUserByUsername('yesamod');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);
        $dto = new ModeratorDto($magazine);
        $dto->user = $user;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:moderators');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/mod/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiOwnerCanAddModeratorsMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $moderator = $this->getUserByUsername('willbeamod');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:moderators');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/mod/{$moderator->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(2, $jsonData['moderators']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][1]);
        self::assertSame($moderator->getId(), $jsonData['moderators'][1]['userId']);
    }

    public function testApiOwnerCanRemoveModeratorsMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $moderator = $this->getUserByUsername('yesamod');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $admin;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:moderators');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(2, $jsonData['moderators']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][1]);
        self::assertSame($moderator->getId(), $jsonData['moderators'][1]['userId']);

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/mod/{$moderator->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertIsArray($jsonData['moderators']);
        self::assertCount(1, $jsonData['moderators']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MODERATOR_RESPONSE_KEYS, $jsonData['moderators'][0]);
        self::assertSame($user->getId(), $jsonData['moderators'][0]['userId']);
    }
}

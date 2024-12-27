<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Admin;

use App\DTO\ModeratorDto;
use App\Tests\Functional\Controller\Api\Magazine\MagazineRetrieveApiTest;
use App\Tests\WebTestCase;

class MagazineTagsApiTest extends WebTestCase
{
    public function testApiCannotAddTagsToMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/tag/test");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRemoveTagsFromMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $magazine->tags = ['test'];
        $entityManager = $this->entityManager;
        $entityManager->persist($magazine);
        $entityManager->flush();

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/tag/test");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotAddTagsToMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotRemoveTagsFromMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $magazine->tags = ['test'];
        $entityManager = $this->entityManager;
        $entityManager->persist($magazine);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiModCannotAddTagsMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:tags');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiModCannotRemoveTagsMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->magazineManager;
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $magazine->tags = ['test'];
        $entityManager = $this->entityManager;
        $entityManager->persist($magazine);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:tags');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiOwnerCanAddTagsMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:tags');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertIsArray($jsonData['tags']);
        self::assertCount(1, $jsonData['tags']);
        self::assertEquals('test', $jsonData['tags'][0]);
    }

    public function testApiOwnerCannotAddWeirdTagsMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:tags');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/tag/test%20Weird", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testApiOwnerCanRemoveTagsMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $magazine->tags = ['test'];
        $entityManager = $this->entityManager;
        $entityManager->persist($magazine);
        $entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:tags');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertIsArray($jsonData['tags']);
        self::assertCount(1, $jsonData['tags']);
        self::assertEquals('test', $jsonData['tags'][0]);

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/tag/test", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertEmpty($jsonData['tags']);
    }
}

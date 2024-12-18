<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Admin;

use App\DTO\ModeratorDto;
use App\Service\MagazineManager;
use App\Tests\WebTestCase;

class MagazinePurgeApiTest extends WebTestCase
{
    public function testApiCannotPurgeMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotPurgeMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonAdminUserCannotPurgeMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write admin:magazine:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiModCannotPurgeMagazine(): void
    {
        $moderator = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($moderator);
        $owner = $this->getUserByUsername('JaneDoe');
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);
        $magazineManager = $this->getService(MagazineManager::class);
        $dto = new ModeratorDto($magazine);
        $dto->user = $moderator;
        $dto->addedBy = $owner;
        $magazineManager->addModerator($dto);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write admin:magazine:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiOwnerCannotPurgeMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write admin:magazine:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiAdminCanPurgeMagazine(): void
    {
        $admin = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $owner = $this->getUserByUsername('JaneDoe');
        $this->client->loginUser($admin);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test', $owner);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write admin:magazine:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/magazine/{$magazine->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(204);
    }
}

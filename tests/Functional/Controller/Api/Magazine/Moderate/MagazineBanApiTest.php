<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Moderate;

use App\DTO\MagazineBanDto;
use App\Service\MagazineManager;
use App\Tests\Functional\Controller\Api\Magazine\MagazineRetrieveApiTest;
use App\Tests\WebTestCase;

class MagazineBanApiTest extends WebTestCase
{
    public function testApiCannotCreateMagazineBanAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('testuser');
        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateMagazineBanWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('testuser');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotCreateMagazineBanIfNotMod(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));
        $user = $this->getUserByUsername('testuser');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:ban:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('POST', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateMagazineBan(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');

        $bannedUser = $this->getUserByUsername('hapless_fool');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:ban:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $reason = 'you got banned through the API, how does that make you feel?';
        $expiredAt = (new \DateTimeImmutable('+1 hour'))->format(\DateTimeImmutable::ATOM);

        $this->client->jsonRequest(
            'POST', "/api/moderate/magazine/{$magazine->getId()}/ban/{$bannedUser->getId()}",
            parameters: [
                'reason' => $reason,
                'expiredAt' => $expiredAt,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveBansApiTest::BAN_RESPONSE_KEYS, $jsonData);
        self::assertEquals($reason, $jsonData['reason']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['bannedUser']);
        self::assertSame($bannedUser->getId(), $jsonData['bannedUser']['userId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['bannedBy']);
        self::assertSame($user->getId(), $jsonData['bannedBy']['userId']);
        self::assertEquals($expiredAt, $jsonData['expiredAt']);
        self::assertFalse($jsonData['expired']);
    }

    public function testApiCannotDeleteMagazineBanAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('testuser');
        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDeleteMagazineBanWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('testuser');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotDeleteMagazineBanIfNotMod(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test', $this->getUserByUsername('JaneDoe'));
        $user = $this->getUserByUsername('testuser');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:ban:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/ban/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanDeleteMagazineBan(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();
        $magazine = $this->getMagazineByName('test');
        $bannedUser = $this->getUserByUsername('hapless_fool');

        $magazineManager = $this->getService(MagazineManager::class);
        $ban = MagazineBanDto::create('test ban <3');
        $magazineManager->ban($magazine, $bannedUser, $user, $ban);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine:ban:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $expiredAt = (new \DateTimeImmutable('+10 seconds'));

        $this->client->request('DELETE', "/api/moderate/magazine/{$magazine->getId()}/ban/{$bannedUser->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(MagazineRetrieveBansApiTest::BAN_RESPONSE_KEYS, $jsonData);
        self::assertEquals($ban->reason, $jsonData['reason']);
        self::assertArrayKeysMatch(MagazineRetrieveApiTest::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['bannedUser']);
        self::assertSame($bannedUser->getId(), $jsonData['bannedUser']['userId']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['bannedBy']);
        self::assertSame($user->getId(), $jsonData['bannedBy']['userId']);

        $actualExpiry = \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM, $jsonData['expiredAt']);
        // Hopefully the API responds fast enough that there is only a max delta of 1 second between these two timestamps
        self::assertEqualsWithDelta($expiredAt->getTimestamp(), $actualExpiry->getTimestamp(), 1.0);
        self::assertTrue($jsonData['expired']);
    }
}

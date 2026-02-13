<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Moderate;

use App\Entity\MagazineOwnershipRequest;
use App\Entity\ModeratorRequest;
use App\Tests\WebTestCase;

class MagazineModOwnerRequestApiTest extends WebTestCase
{
    public function testApiCannotToggleModRequestAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/toggle");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotToggleModRequestWithoutScope(): void
    {
        $magazine = $this->getMagazineByName('test');

        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanToggleModRequest(): void
    {
        $magazine = $this->getMagazineByName('test');

        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(['created'], $jsonData);
        self::assertTrue($jsonData['created']);

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(['created'], $jsonData);
        self::assertFalse($jsonData['created']);
    }

    public function testApiCannotAcceptModRequestAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/accept/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotAcceptModRequestWithoutScope(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/accept/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanAcceptModRequest(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoeTheSecond');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/accept/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $modRequest = $this->entityManager->getRepository(ModeratorRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);
        self::assertNull($modRequest);

        $magazine = $this->magazineRepository->findOneBy(['id' => $magazine->getId()]);
        $user = $this->userRepository->findOneBy(['id' => $user->getId()]);
        self::assertTrue($magazine->userIsModerator($user));
    }

    public function testApiCanRejectModRequest(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoeTheSecond');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/modRequest/reject/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $modRequest = $this->entityManager->getRepository(ModeratorRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);
        self::assertNull($modRequest);

        $magazine = $this->magazineRepository->findOneBy(['id' => $magazine->getId()]);
        $user = $this->userRepository->findOneBy(['id' => $user->getId()]);
        self::assertFalse($magazine->userIsModerator($user));
    }

    public function testApiCannotToggleOwnerRequestAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/toggle");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotToggleOwnerRequestWithoutScope(): void
    {
        $magazine = $this->getMagazineByName('test');

        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanToggleOwnerRequest(): void
    {
        $magazine = $this->getMagazineByName('test');

        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'magazine:subscribe');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(['created'], $jsonData);
        self::assertTrue($jsonData['created']);

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/toggle", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(['created'], $jsonData);
        self::assertFalse($jsonData['created']);
    }

    public function testApiCannotAcceptOwnerRequestAnonymously(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/accept/{$user->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotAcceptOwnerRequestWithoutScope(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoe');
        $this->magazineManager->toggleModeratorRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/accept/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanAcceptOwnerRequest(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoeTheSecond');
        $this->magazineManager->toggleOwnershipRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/accept/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $ownerRequest = $this->entityManager->getRepository(MagazineOwnershipRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);
        self::assertNull($ownerRequest);

        $magazine = $this->magazineRepository->findOneBy(['id' => $magazine->getId()]);
        $user = $this->userRepository->findOneBy(['id' => $user->getId()]);
        self::assertTrue($magazine->userIsOwner($user));
    }

    public function testApiCanRejectOwnerRequest(): void
    {
        $magazine = $this->getMagazineByName('test');
        $user = $this->getUserByUsername('JohnDoeTheSecond');
        $this->magazineManager->toggleOwnershipRequest($magazine, $user);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}/ownerRequest/reject/{$user->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $this->client->getResponse()->getContent());

        $ownerRequest = $this->entityManager->getRepository(ModeratorRequest::class)->findOneBy([
            'magazine' => $magazine,
            'user' => $user,
        ]);
        self::assertNull($ownerRequest);

        $magazine = $this->magazineRepository->findOneBy(['id' => $magazine->getId()]);
        $user = $this->userRepository->findOneBy(['id' => $user->getId()]);
        self::assertFalse($magazine->userIsOwner($user));
        self::assertFalse($magazine->userIsModerator($user));
    }
}

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

    public function testApiCannotListModRequestsAnonymously(): void
    {
        $this->client->request('GET', '/api/moderate/modRequest/list');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotListModRequestsWithoutScope(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('GET', '/api/moderate/modRequest/list', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotListModRequestsForInvalidMagazineId(): void
    {
        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/modRequest/list?magazine=a', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiCannotListModRequestsForMissingMagazine(): void
    {
        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/modRequest/list?magazine=99', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiCanListModRequestsForMagazine(): void
    {
        $magazine1 = $this->getMagazineByName('Magazine 1');
        $magazine2 = $this->getMagazineByName('Magazine 2');
        $magazine3 = $this->getMagazineByName('Magazine 3');
        $user1 = $this->getUserByUsername('User 1');
        $user2 = $this->getUserByUsername('User 2');

        $this->magazineManager->toggleModeratorRequest($magazine1, $user1);
        $this->magazineManager->toggleModeratorRequest($magazine1, $user2);
        $this->magazineManager->toggleModeratorRequest($magazine2, $user2);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/moderate/modRequest/list?magazine={$magazine1->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);

        self::assertCount(2, $jsonData);
        self::assertTrue(array_all($jsonData, function ($item) use ($magazine1, $user1, $user2) {
            return $item['magazine']['magazineId'] === $magazine1->getId()
                && ($item['user']['userId'] === $user1->getId() || $item['user']['userId'] === $user2->getId());
        }));
        self::assertNotSame($jsonData[0]['user']['userId'], $jsonData[1]['user']['userId']);
    }

    public function testApiCanListModRequestsForAllMagazines(): void
    {
        $magazine1 = $this->getMagazineByName('Magazine 1');
        $magazine2 = $this->getMagazineByName('Magazine 2');
        $magazine3 = $this->getMagazineByName('Magazine 3');
        $user1 = $this->getUserByUsername('User 1');
        $user2 = $this->getUserByUsername('User 2');

        $this->magazineManager->toggleModeratorRequest($magazine1, $user1);
        $this->magazineManager->toggleModeratorRequest($magazine1, $user2);
        $this->magazineManager->toggleModeratorRequest($magazine2, $user2);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/modRequest/list', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);

        self::assertCount(3, $jsonData);
        self::assertTrue(array_all($jsonData, function ($item) use ($magazine1, $magazine2, $user1, $user2) {
            return ($item['magazine']['magazineId'] === $magazine1->getId() && $item['user']['userId'] === $user1->getId())
                || ($item['magazine']['magazineId'] === $magazine1->getId() && $item['user']['userId'] === $user2->getId())
                || ($item['magazine']['magazineId'] === $magazine2->getId() && $item['user']['userId'] === $user2->getId());
        }));
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

    public function testApiCannotListOwnerRequestsAnonymously(): void
    {
        $this->client->request('GET', '/api/moderate/ownerRequest/list');

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotListOwnerRequestsWithoutScope(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('GET', '/api/moderate/ownerRequest/list', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotListOwnerRequestsForInvalidMagazineId(): void
    {
        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/ownerRequest/list?magazine=a', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiCannotListOwnerRequestsForMissingMagazine(): void
    {
        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/ownerRequest/list?magazine=99', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiCanListOwnerRequestsForMagazine(): void
    {
        $magazine1 = $this->getMagazineByName('Magazine 1');
        $magazine2 = $this->getMagazineByName('Magazine 2');
        $magazine3 = $this->getMagazineByName('Magazine 3');
        $user1 = $this->getUserByUsername('User 1');
        $user2 = $this->getUserByUsername('User 2');

        $this->magazineManager->toggleOwnershipRequest($magazine1, $user1);
        $this->magazineManager->toggleOwnershipRequest($magazine1, $user2);
        $this->magazineManager->toggleOwnershipRequest($magazine2, $user2);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/moderate/ownerRequest/list?magazine={$magazine1->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);

        self::assertCount(2, $jsonData);
        self::assertTrue(array_all($jsonData, function ($item) use ($magazine1, $user1, $user2) {
            return $item['magazine']['magazineId'] === $magazine1->getId()
                && ($item['user']['userId'] === $user1->getId() || $item['user']['userId'] === $user2->getId());
        }));
        self::assertNotSame($jsonData[0]['user']['userId'], $jsonData[1]['user']['userId']);
    }

    public function testApiCanListOwnerRequestsForAllMagazines(): void
    {
        $magazine1 = $this->getMagazineByName('Magazine 1');
        $magazine2 = $this->getMagazineByName('Magazine 2');
        $magazine3 = $this->getMagazineByName('Magazine 3');
        $user1 = $this->getUserByUsername('User 1');
        $user2 = $this->getUserByUsername('User 2');

        $this->magazineManager->toggleOwnershipRequest($magazine1, $user1);
        $this->magazineManager->toggleOwnershipRequest($magazine1, $user2);
        $this->magazineManager->toggleOwnershipRequest($magazine2, $user2);

        $adminUser = $this->getUserByUsername('Admin');
        $this->setAdmin($adminUser);
        $this->client->loginUser($adminUser);
        self::createOAuth2AuthCodeClient();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'admin:magazine:moderate');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/moderate/ownerRequest/list', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData);

        self::assertCount(3, $jsonData);
        self::assertTrue(array_all($jsonData, function ($item) use ($magazine1, $magazine2, $user1, $user2) {
            return ($item['magazine']['magazineId'] === $magazine1->getId() && $item['user']['userId'] === $user1->getId())
                || ($item['magazine']['magazineId'] === $magazine1->getId() && $item['user']['userId'] === $user2->getId())
                || ($item['magazine']['magazineId'] === $magazine2->getId() && $item['user']['userId'] === $user2->getId());
        }));
    }
}

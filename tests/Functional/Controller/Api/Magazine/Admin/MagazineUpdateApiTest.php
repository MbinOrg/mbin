<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Magazine\Admin;

use App\Tests\WebTestCase;

class MagazineUpdateApiTest extends WebTestCase
{
    public function testApiCannotUpdateMagazineAnonymous(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpdateMagazineWithoutScope(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client);
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/moderate/magazine/{$magazine->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpdateMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');
        $magazine->rules = 'Some initial rules';
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:update');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $name = 'test';
        $title = 'API Test Magazine';
        $description = 'A description';
        $rules = 'Some rules';

        $this->client->jsonRequest(
            'PUT', "/api/moderate/magazine/{$magazine->getId()}",
            parameters: [
                'name' => $name,
                'title' => $title,
                'description' => $description,
                'rules' => $rules,
                'isAdult' => true,
                'discoverable' => false,
                'indexable' => false,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(WebTestCase::MAGAZINE_RESPONSE_KEYS, $jsonData);
        self::assertEquals($name, $jsonData['name']);
        self::assertSame($user->getId(), $jsonData['owner']['userId']);
        self::assertEquals($description, $jsonData['description']);
        self::assertEquals($rules, $jsonData['rules']);
        self::assertTrue($jsonData['isAdult']);
        self::assertFalse($jsonData['discoverable']);
        self::assertFalse($jsonData['indexable']);
    }

    public function testApiCannotUpdateMagazineWithInvalidParams(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        self::createOAuth2AuthCodeClient();

        $magazine = $this->getMagazineByName('test');

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read write moderate:magazine_admin:update');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $name = 'someothername';
        $title = 'Different name';
        $description = 'A description';

        $this->client->jsonRequest(
            'PUT', "/api/moderate/magazine/{$magazine->getId()}",
            parameters: [
                'name' => $name,
                'title' => $title,
                'description' => $description,
                'isAdult' => false,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(400);

        $description = 'short title';
        $title = 'as';
        $this->client->jsonRequest(
            'PUT', "/api/moderate/magazine/{$magazine->getId()}",
            parameters: [
                'title' => $title,
                'description' => $description,
                'isAdult' => false,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(400);

        $description = 'long title';
        $title = 'Way too long of a title. This can only be 50 characters!';
        $this->client->jsonRequest(
            'PUT', "/api/moderate/magazine/{$magazine->getId()}",
            parameters: [
                'title' => $title,
                'description' => $description,
                'isAdult' => false,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(400);

        $rules = 'Some rules';
        $description = 'Rules are deprecated';
        $this->client->jsonRequest(
            'PUT', "/api/moderate/magazine/{$magazine->getId()}",
            parameters: [
                'rules' => $rules,
                'description' => $description,
                'isAdult' => false,
            ],
            server: ['HTTP_AUTHORIZATION' => $token]
        );
        self::assertResponseStatusCodeSame(400);
    }
}

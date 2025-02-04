<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Bookmark;

use App\DTO\BookmarkListDto;
use App\Entity\User;
use App\Tests\WebTestCase;

class BookmarkListApiTest extends WebTestCase
{
    private User $user;
    private string $token;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->getUserByUsername('user');
        $this->client->loginUser($this->user);
        self::createOAuth2PublicAuthCodeClient();
        $codes = self::getPublicAuthorizationCodeTokenResponse($this->client, scopes: 'user:bookmark_list');
        $this->token = $codes['token_type'].' '.$codes['access_token'];
    }

    public function testCreateList(): void
    {
        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(0, $jsonData['items']);

        $this->client->request('POST', '/api/bookmark-lists/test-list', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertEquals('test-list', $jsonData['name']);
        self::assertEquals(0, $jsonData['count']);
        self::assertFalse($jsonData['isDefault']);

        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertEquals('test-list', $jsonData['items'][0]['name']);
        self::assertEquals(0, $jsonData['items'][0]['count']);
        self::assertFalse($jsonData['items'][0]['isDefault']);
    }

    public function testRenameList(): void
    {
        $dto = new BookmarkListDto();
        $dto->name = 'new-test-list';

        $this->client->request('POST', '/api/bookmark-lists/test-list', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/bookmark-lists/test-list', parameters: $dto->jsonSerialize(), server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertEquals('new-test-list', $jsonData['name']);
        self::assertEquals(0, $jsonData['count']);
        self::assertFalse($jsonData['isDefault']);

        $dto = new BookmarkListDto();
        $dto->name = 'new-test-list2';
        $dto->isDefault = true;

        $this->client->jsonRequest('PUT', '/api/bookmark-lists/new-test-list', parameters: $dto->jsonSerialize(), server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertEquals('new-test-list2', $jsonData['name']);
        self::assertEquals(0, $jsonData['count']);
        self::assertTrue($jsonData['isDefault']);
    }

    public function testDeleteList(): void
    {
        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(0, $jsonData['items']);

        $this->client->request('POST', '/api/bookmark-lists/test-list', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);

        $this->client->request('DELETE', '/api/bookmark-lists/test-list', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(0, $jsonData['items']);
    }

    public function testMakeListDefault(): void
    {
        $this->client->request('POST', '/api/bookmark-lists/test-list', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('PUT', '/api/bookmark-lists/test-list/makeDefault', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertEquals('test-list', $jsonData['name']);
        self::assertEquals(0, $jsonData['count']);
        self::assertTrue($jsonData['isDefault']);

        $this->client->request('GET', '/api/bookmark-lists', server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['items'][0]);

        self::assertEquals('test-list', $jsonData['items'][0]['name']);
        self::assertEquals(0, $jsonData['items'][0]['count']);
        self::assertTrue($jsonData['items'][0]['isDefault']);
    }
}

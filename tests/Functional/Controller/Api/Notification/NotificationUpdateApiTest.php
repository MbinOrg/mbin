<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Notification;

use App\Entity\User;
use App\Tests\WebTestCase;

class NotificationUpdateApiTest extends WebTestCase
{
    private User $user;
    private string $token;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = $this->getUserByUsername('user');
        $this->client->loginUser($this->user);
        self::createOAuth2PublicAuthCodeClient();
        $codes = self::getPublicAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:edit');
        $this->token = $codes['token_type'].' '.$codes['access_token'];
        // it seems that the oauth flow detaches the user object from the entity manager, so fetch it again
        $this->user = $this->userRepository->findOneByUsername('user');
    }

    public function testSetEntryNotificationSetting(): void
    {
        $entry = $this->getEntryByTitle('entry');
        $this->testAllSettings("/api/entry/{$entry->getId()}", "/api/notification/update/entry/{$entry->getId()}");
    }

    public function testSetPostNotificationSetting(): void
    {
        $post = $this->createPost('post');
        $this->testAllSettings("/api/post/{$post->getId()}", "/api/notification/update/post/{$post->getId()}");
    }

    public function testSetUserNotificationSetting(): void
    {
        $user2 = $this->getUserByUsername('test');
        $this->testAllSettings("/api/users/{$user2->getId()}", "/api/notification/update/user/{$user2->getId()}");
    }

    public function testSetMagazineNotificationSetting(): void
    {
        $magazine = $this->getMagazineByName('test');
        $this->testAllSettings("/api/magazine/{$magazine->getId()}", "/api/notification/update/magazine/{$magazine->getId()}");
    }

    private function testAllSettings(string $retrieveUrl, string $updateUrl): void
    {
        $this->client->request('GET', $retrieveUrl, server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertEquals('Default', $jsonData['notificationStatus']);

        $this->client->request('PUT', "$updateUrl/Loud", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', $retrieveUrl, server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertEquals('Loud', $jsonData['notificationStatus']);

        $this->client->request('PUT', "$updateUrl/Muted", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', $retrieveUrl, server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertEquals('Muted', $jsonData['notificationStatus']);

        $this->client->request('PUT', "$updateUrl/Default", server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', $retrieveUrl, server: ['HTTP_AUTHORIZATION' => $this->token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);
        self::assertEquals('Default', $jsonData['notificationStatus']);
    }
}

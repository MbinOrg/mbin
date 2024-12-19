<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Notification;

use App\Entity\Notification;
use App\Tests\WebTestCase;

class NotificationReadApiTest extends WebTestCase
{
    public function testApiCannotMarkNotificationReadAnonymous(): void
    {
        $notification = $this->createMessageNotification();

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/read");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotMarkNotificationReadWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/read", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotMarkOtherUsersNotificationRead(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $messagedUser = $this->getUserByUsername('JamesDoe');
        $notification = $this->createMessageNotification($messagedUser);

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/read", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanMarkNotificationRead(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/read", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(NotificationRetrieveApiTest::NOTIFICATION_RESPONSE_KEYS, $jsonData);
        self::assertEquals('read', $jsonData['status']);
        self::assertEquals('message_notification', $jsonData['type']);

        self::assertIsArray($jsonData['subject']);
        self::assertArrayKeysMatch(self::MESSAGE_RESPONSE_KEYS, $jsonData['subject']);
        self::assertNull($jsonData['subject']['messageId']);
        self::assertNull($jsonData['subject']['threadId']);
        self::assertNull($jsonData['subject']['sender']);
        self::assertNull($jsonData['subject']['status']);
        self::assertNull($jsonData['subject']['createdAt']);
        self::assertEquals('This app has not received permission to read your messages.', $jsonData['subject']['body']);
    }

    public function testApiCannotMarkNotificationUnreadAnonymous(): void
    {
        $notification = $this->createMessageNotification();

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/unread");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotMarkNotificationUnreadWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/unread", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotMarkOtherUsersNotificationUnread(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $messagedUser = $this->getUserByUsername('JamesDoe');
        $notification = $this->createMessageNotification($messagedUser);

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/unread", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanMarkNotificationUnread(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();
        $notification->status = Notification::STATUS_READ;
        $entityManager = $this->entityManager;
        $entityManager->persist($notification);
        $entityManager->flush();

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/notifications/{$notification->getId()}/unread", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(NotificationRetrieveApiTest::NOTIFICATION_RESPONSE_KEYS, $jsonData);
        self::assertEquals('new', $jsonData['status']);
        self::assertEquals('message_notification', $jsonData['type']);

        self::assertIsArray($jsonData['subject']);
        self::assertArrayKeysMatch(self::MESSAGE_RESPONSE_KEYS, $jsonData['subject']);
        self::assertNull($jsonData['subject']['messageId']);
        self::assertNull($jsonData['subject']['threadId']);
        self::assertNull($jsonData['subject']['sender']);
        self::assertNull($jsonData['subject']['status']);
        self::assertNull($jsonData['subject']['createdAt']);
        self::assertEquals('This app has not received permission to read your messages.', $jsonData['subject']['body']);
    }

    public function testApiCannotMarkAllNotificationsReadAnonymous(): void
    {
        $this->createMessageNotification();

        $this->client->request('PUT', '/api/notifications/read');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotMarkAllNotificationsReadWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $this->createMessageNotification();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/notifications/read', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanMarkAllNotificationsRead(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');

        $notification = $this->createMessageNotification();

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', '/api/notifications/read', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $notificationRepository = $this->notificationRepository;
        $notification = $notificationRepository->find($notification->getId());
        self::assertNotNull($notification);
        self::assertEquals('read', $notification->status);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Notification;

use App\Tests\WebTestCase;

class NotificationDeleteApiTest extends WebTestCase
{
    public function testApiCannotDeleteNotificationByIdAnonymous(): void
    {
        $notification = $this->createMessageNotification();

        $this->client->request('DELETE', "/api/notifications/{$notification->getId()}");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDeleteNotificationByIdWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/notifications/{$notification->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotDeleteOtherUsersNotificationById(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $messagedUser = $this->getUserByUsername('JamesDoe');
        $notification = $this->createMessageNotification($messagedUser);

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/notifications/{$notification->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanDeleteNotificationById(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $notification = $this->createMessageNotification();

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/notifications/{$notification->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $notificationRepository = $this->notificationRepository;
        $notification = $notificationRepository->find($notification->getId());
        self::assertNull($notification);
    }

    public function testApiCannotDeleteAllNotificationsAnonymous(): void
    {
        $this->createMessageNotification();

        $this->client->request('DELETE', '/api/notifications');
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDeleteAllNotificationsWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $this->createMessageNotification();

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', '/api/notifications', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanDeleteAllNotifications(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');

        $notification = $this->createMessageNotification();

        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', '/api/notifications', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $notificationRepository = $this->notificationRepository;
        $notification = $notificationRepository->find($notification->getId());
        self::assertNull($notification);
    }
}

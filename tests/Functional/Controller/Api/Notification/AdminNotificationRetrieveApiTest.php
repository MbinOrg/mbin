<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Notification;

use App\DTO\UserDto;
use App\Tests\WebTestCase;

class AdminNotificationRetrieveApiTest extends WebTestCase
{
    public const array USER_SIGNUP_RESPONSE_KEYS = ['userId', 'username', 'isBot', 'createdAt', 'email', 'applicationText'];

    public function testApiCanReturnNotificationForUserSignup()
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe', isAdmin: true);
        $this->client->loginUser($user);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read user:notification:read user:message:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $createdAt = new \DateTimeImmutable();
        $createDto = UserDto::create('new_here', email: 'user@example.com', createdAt: $createdAt, applicationText: 'hello there');
        $createDto->plainPassword = '1234';
        $this->userManager->create($createDto, false, false, false);

        $this->client->request('GET', '/api/notifications/all', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertCount(1, $jsonData['items']);

        $item = $jsonData['items'][0];
        self::assertArrayKeysMatch(NotificationRetrieveApiTest::NOTIFICATION_RESPONSE_KEYS, $item);
        self::assertEquals('new_signup', $item['type']);
        self::assertEquals('new', $item['status']);
        self::assertNull($item['reportId']);

        $subject = $item['subject'];
        self::assertIsArray($subject);
        self::assertArrayKeysMatch(self::USER_SIGNUP_RESPONSE_KEYS, $subject);
        self::assertNotEquals(0, $subject['userId']);
        self::assertEquals('new_here', $subject['username']);
        self::assertEquals('user@example.com', $subject['email']);
        self::assertEquals($createdAt->format(\DateTimeInterface::ATOM), $subject['createdAt']);
        self::assertEquals('hello there', $subject['applicationText']);
    }
}

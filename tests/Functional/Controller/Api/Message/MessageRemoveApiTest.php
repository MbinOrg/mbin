<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Message;

use App\DTO\MessageDto;
use App\Entity\User;
use App\Tests\WebTestCase;

class MessageRemoveApiTest extends WebTestCase
{
    public function testApiCannotRemoveMessagesAnonymous(): void
    {
        $message = $this->createMessage($this->getUserByUsername('JohnDoe'), $this->getUserByUsername('JaneDoe'), 'test message');
        $this->client->request('DELETE', "/api/messages/thread/{$message->thread->getId()}");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRemoveMessagesWithoutScope(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $from = $this->getUserByUsername('JaneDoe');
        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        $message = $this->createMessage($user, $from, 'test message');

        $this->client->request('DELETE', "/api/messages/thread/{$message->thread->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRemoveThread(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:message:delete user:message:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $from = $this->getUserByUsername('JaneDoe');
        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        $message = $this->createMessage($user, $from, 'test message');

        $this->client->request('DELETE', "/api/messages/thread/{$message->thread->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/messages/thread/{$message->thread->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testRemovedThreadIgnoresNewMessages(): void
    {
        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:message:delete user:message:read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $from = $this->getUserByUsername('JaneDoe');
        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        $message = $this->createMessage($user, $from, 'test message');

        $this->client->request('DELETE', "/api/messages/thread/{$message->thread->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $messageDto = new MessageDto();
        $messageDto->body = 'test message';
        $message2 = $this->messageManager->toMessage($messageDto, $message->thread, $from);
        self::assertSame($message->thread->getId(), $message2->thread->getId());

        $this->client->request('GET', "/api/messages/thread/{$message->thread->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }
}

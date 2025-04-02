<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment;

use App\Tests\WebTestCase;

class EntryCommentDeleteApiTest extends WebTestCase
{
    public function testApiCannotDeleteCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->request('DELETE', "/api/comments/{$comment->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDeleteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotDeleteOtherUsersComment(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('other');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user2);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanDeleteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $commentRepository = $this->entryCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(204);
        $comment = $commentRepository->find($comment->getId());
        self::assertNull($comment);
    }

    public function testApiCanSoftDeleteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);
        $this->createEntryComment('test comment', $entry, $user, $comment);

        $commentRepository = $this->entryCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:delete');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/comments/{$comment->getId()}", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(204);
        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
        self::assertTrue($comment->isSoftDeleted());
    }
}

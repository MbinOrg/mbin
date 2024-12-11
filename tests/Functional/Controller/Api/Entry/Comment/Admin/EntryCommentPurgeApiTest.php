<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment\Admin;

use App\Repository\EntryCommentRepository;
use App\Tests\WebTestCase;

class EntryCommentPurgeApiTest extends WebTestCase
{
    public function testApiCannotPurgeArticleEntryAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge");
        self::assertResponseStatusCodeSame(401);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCannotPurgeArticleEntryWithoutScope(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $user, magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiNonAdminCannotPurgeComment(): void
    {
        $otherUser = $this->getUserByUsername('somebody');
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $otherUser, magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCanPurgeComment(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $entry = $this->getEntryByTitle('test article', body: 'test for deletion', user: $user, magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $comment = $commentRepository->find($comment->getId());
        self::assertNull($comment);
    }

    public function testApiCannotPurgeImageCommentAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, imageDto: $imageDto);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge");
        self::assertResponseStatusCodeSame(401);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCannotPurgeImageCommentWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user', isAdmin: true);

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, imageDto: $imageDto);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiNonAdminCannotPurgeImageComment(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, imageDto: $imageDto);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCanPurgeImageComment(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test image', body: 'test', magazine: $magazine);
        $comment = $this->createEntryComment('test comment', $entry, imageDto: $imageDto);

        $commentRepository = $this->getService(EntryCommentRepository::class);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:entry_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $comment = $commentRepository->find($comment->getId());
        self::assertNull($comment);
    }
}

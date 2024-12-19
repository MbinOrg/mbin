<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post\Comment\Admin;

use App\Tests\WebTestCase;

class PostCommentPurgeApiTest extends WebTestCase
{
    public function testApiCannotPurgeCommentAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test article', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $commentRepository = $this->postCommentRepository;

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge");
        self::assertResponseStatusCodeSame(401);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCannotPurgeCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test article', user: $user, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiNonAdminCannotPurgeComment(): void
    {
        $otherUser = $this->getUserByUsername('somebody');
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test article', user: $otherUser, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:post_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCanPurgeComment(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test article', user: $user, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:post_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $comment = $commentRepository->find($comment->getId());
        self::assertNull($comment);
    }

    public function testApiCannotPurgeImageCommentAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $post = $this->createPost('test image', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, imageDto: $imageDto);

        $commentRepository = $this->postCommentRepository;

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge");
        self::assertResponseStatusCodeSame(401);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCannotPurgeImageCommentWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user', isAdmin: true);

        $imageDto = $this->getKibbyImageDto();
        $post = $this->createPost('test image', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, imageDto: $imageDto);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiNonAdminCannotPurgeImageComment(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $post = $this->createPost('test image', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, imageDto: $imageDto);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:post_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $comment = $commentRepository->find($comment->getId());
        self::assertNotNull($comment);
    }

    public function testApiCanPurgeImageComment(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');

        $imageDto = $this->getKibbyImageDto();
        $post = $this->createPost('test image', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, imageDto: $imageDto);

        $commentRepository = $this->postCommentRepository;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($admin);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read admin:post_comment:purge');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('DELETE', "/api/admin/post-comment/{$comment->getId()}/purge", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(204);

        $comment = $commentRepository->find($comment->getId());
        self::assertNull($comment);
    }
}

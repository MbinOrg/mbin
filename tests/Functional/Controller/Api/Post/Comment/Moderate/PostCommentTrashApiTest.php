<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post\Comment\Moderate;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class PostCommentTrashApiTest extends WebTestCase
{
    public function testApiCannotTrashCommentAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('a post', $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/trash");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotTrashCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('a post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotTrashComment(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('a post', $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanTrashComment(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByName('acme');
        $user2 = $this->getUserByUsername('other');
        $post = $this->createPost('a post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/trash", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment->getId(), $jsonData['commentId']);
        self::assertSame('test comment', $jsonData['body']);
        self::assertSame('trashed', $jsonData['visibility']);
    }

    public function testApiCannotRestoreCommentAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('a post', $magazine);
        $comment = $this->createPostComment('test comment', $post);

        $postCommentManager = $this->postCommentManager;
        $postCommentManager->trash($this->getUserByUsername('user'), $comment);

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/restore");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRestoreCommentWithoutScope(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('a post', $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        $postCommentManager = $this->postCommentManager;
        $postCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotRestoreComment(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('a post', $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        $postCommentManager = $this->postCommentManager;
        $postCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRestoreComment(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $user2 = $this->getUserByUsername('other');
        $post = $this->createPost('a post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $user2);

        $magazineManager = $this->magazineManager;
        $moderator = new ModeratorDto($magazine);
        $moderator->user = $user;
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $postCommentManager = $this->postCommentManager;
        $postCommentManager->trash($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:trash');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/restore", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment->getId(), $jsonData['commentId']);
        self::assertSame('test comment', $jsonData['body']);
        self::assertSame('visible', $jsonData['visibility']);
    }
}

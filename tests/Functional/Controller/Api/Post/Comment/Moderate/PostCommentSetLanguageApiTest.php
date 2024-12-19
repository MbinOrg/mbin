<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post\Comment\Moderate;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class PostCommentSetLanguageApiTest extends WebTestCase
{
    public function testApiCannotSetCommentLanguageAnonymous(): void
    {
        $post = $this->createPost('a post');
        $comment = $this->createPostComment('test comment', $post);

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/de");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotSetCommentLanguageWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $magazine = $this->getMagazineByName('acme');
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

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/de", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiNonModCannotSetCommentLanguage(): void
    {
        $user = $this->getUserByUsername('user');
        $user2 = $this->getUserByUsername('user2');
        $post = $this->createPost('a post');
        $comment = $this->createPostComment('test comment', $post, $user2);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:language');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/de", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanSetCommentLanguage(): void
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

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read moderate:post_comment:language');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/moderate/post-comment/{$comment->getId()}/de", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment->getId(), $jsonData['commentId']);
        self::assertSame('test comment', $jsonData['body']);
        self::assertSame('de', $jsonData['lang']);
    }
}

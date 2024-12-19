<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post\Comment;

use App\Tests\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PostCommentCreateApiTest extends WebTestCase
{
    public function testApiCannotCreateCommentAnonymous(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments",
            parameters: $comment
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateCommentWithoutScope(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments",
            parameters: $comment, server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateComment(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post_comment:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments",
            parameters: $comment, server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment['body'], $jsonData['body']);
        self::assertSame($comment['lang'], $jsonData['lang']);
        self::assertSame($comment['isAdult'], $jsonData['isAdult']);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($post->magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertNull($jsonData['rootId']);
        self::assertNull($jsonData['parentId']);
    }

    public function testApiCannotCreateCommentReplyAnonymous(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply",
            parameters: $comment
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateCommentReplyWithoutScope(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply",
            parameters: $comment, server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateCommentReply(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post_comment:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply",
            parameters: $comment, server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment['body'], $jsonData['body']);
        self::assertSame($comment['lang'], $jsonData['lang']);
        self::assertSame($comment['isAdult'], $jsonData['isAdult']);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($post->magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertSame($postComment->getId(), $jsonData['rootId']);
        self::assertSame($postComment->getId(), $jsonData['parentId']);
    }

    public function testApiCannotCreateImageCommentAnonymous(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
            'alt' => 'It\'s Kibby!',
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        copy($this->kibbyPath, $this->kibbyPath.'.tmp');
        $image = new UploadedFile($this->kibbyPath.'.tmp', 'kibby_emoji.png', 'image/png');

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/image",
            parameters: $comment, files: ['uploadImage' => $image]
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateImageCommentWithoutScope(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
            'alt' => 'It\'s Kibby!',
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = bin2hex(random_bytes(32));
        copy($this->kibbyPath, $tmpPath.'.png');
        $image = new UploadedFile($tmpPath.'.png', 'kibby_emoji.png', 'image/png');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/image",
            parameters: $comment, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateImageComment(): void
    {
        $post = $this->createPost('a post');

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
            'alt' => 'It\'s Kibby!',
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        copy($this->kibbyPath, $this->kibbyPath.'.tmp');
        $image = new UploadedFile($this->kibbyPath.'.tmp', 'kibby_emoji.png', 'image/png');

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post_comment:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/image",
            parameters: $comment, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment['body'], $jsonData['body']);
        self::assertSame($comment['lang'], $jsonData['lang']);
        self::assertSame($comment['isAdult'], $jsonData['isAdult']);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($post->magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertNull($jsonData['rootId']);
        self::assertNull($jsonData['parentId']);
    }

    public function testApiCannotCreateImageCommentReplyAnonymous(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
            'alt' => 'It\'s Kibby!',
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        copy($this->kibbyPath, $this->kibbyPath.'.tmp');
        $image = new UploadedFile($this->kibbyPath.'.tmp', 'kibby_emoji.png', 'image/png');

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply/image",
            parameters: $comment, files: ['uploadImage' => $image]
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotCreateImageCommentReplyWithoutScope(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = bin2hex(random_bytes(32));
        copy($this->kibbyPath, $tmpPath.'.tmp');
        $image = new UploadedFile($tmpPath.'.tmp', 'kibby_emoji.png', 'image/png');

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply/image",
            parameters: $comment, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanCreateImageCommentReply(): void
    {
        $post = $this->createPost('a post');
        $postComment = $this->createPostComment('a comment', $post);

        $comment = [
            'body' => 'Test comment',
            'lang' => 'en',
            'isAdult' => false,
            'alt' => 'It\'s Kibby!',
        ];

        // Uploading a file appears to delete the file at the given path, so make a copy before upload
        $tmpPath = bin2hex(random_bytes(32));
        copy($this->kibbyPath, $tmpPath.'.png');
        $image = new UploadedFile($tmpPath.'.png', 'kibby_emoji.png', 'image/png');

        $imageManager = $this->imageManager;
        $expectedPath = $imageManager->getFilePath($image->getFilename());

        self::createOAuth2AuthCodeClient();
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post_comment:create');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request(
            'POST', "/api/posts/{$post->getId()}/comments/{$postComment->getId()}/reply/image",
            parameters: $comment, files: ['uploadImage' => $image],
            server: ['HTTP_AUTHORIZATION' => $token]
        );

        self::assertResponseStatusCodeSame(201);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame($comment['body'], $jsonData['body']);
        self::assertSame($comment['lang'], $jsonData['lang']);
        self::assertSame($comment['isAdult'], $jsonData['isAdult']);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($post->magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertSame($postComment->getId(), $jsonData['rootId']);
        self::assertSame($postComment->getId(), $jsonData['parentId']);
        self::assertIsArray($jsonData['image']);
        self::assertArrayKeysMatch(self::IMAGE_KEYS, $jsonData['image']);
        self::assertEquals($expectedPath, $jsonData['image']['filePath']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post;

use App\Tests\WebTestCase;

class PostVoteApiTest extends WebTestCase
{
    public function testApiCannotUpvotePostAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', magazine: $magazine);

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/1");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpvotePostWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/1", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpvotePost(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/1", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertEquals($post->body, $jsonData['body']);
        self::assertNull($jsonData['image']);
        self::assertEquals($post->lang, $jsonData['lang']);
        self::assertEmpty($jsonData['tags']);
        self::assertNull($jsonData['mentions']);
        self::assertSame(0, $jsonData['comments']);
        self::assertSame(1, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        // No scope for seeing votes granted
        self::assertFalse($jsonData['isFavourited']);
        self::assertSame(1, $jsonData['userVote']);
        self::assertFalse($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertEquals('test-post', $jsonData['slug']);
        self::assertNull($jsonData['apId']);
    }

    public function testApiCannotDownvotePostAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', magazine: $magazine);

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/-1");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDownvotePostWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/-1", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCannotDownvotePost(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/-1", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testApiCannotClearVotePostAnonymous(): void
    {
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', magazine: $magazine);

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/0");
        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotClearVotePostWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        $voteManager = $this->voteManager;
        $voteManager->vote(1, $post, $user, rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/0", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanClearVotePost(): void
    {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);

        $voteManager = $this->voteManager;
        $voteManager->vote(1, $post, $user, rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read post:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('PUT', "/api/post/{$post->getId()}/vote/0", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData);
        self::assertSame($post->getId(), $jsonData['postId']);
        self::assertIsArray($jsonData['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['magazine']);
        self::assertSame($magazine->getId(), $jsonData['magazine']['magazineId']);
        self::assertIsArray($jsonData['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['user']);
        self::assertSame($user->getId(), $jsonData['user']['userId']);
        self::assertEquals($post->body, $jsonData['body']);
        self::assertNull($jsonData['image']);
        self::assertEquals($post->lang, $jsonData['lang']);
        self::assertEmpty($jsonData['tags']);
        self::assertNull($jsonData['mentions']);
        self::assertSame(0, $jsonData['comments']);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        // No scope for seeing votes granted
        self::assertFalse($jsonData['isFavourited']);
        self::assertSame(0, $jsonData['userVote']);
        self::assertFalse($jsonData['isAdult']);
        self::assertFalse($jsonData['isPinned']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['lastActive'], 'lastActive date format invalid');
        self::assertEquals('test-post', $jsonData['slug']);
        self::assertNull($jsonData['apId']);
    }
}

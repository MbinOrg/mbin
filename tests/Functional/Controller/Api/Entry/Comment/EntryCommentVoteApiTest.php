<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Entry\Comment;

use App\Service\FavouriteManager;
use App\Service\VoteManager;
use App\Tests\WebTestCase;

class EntryCommentVoteApiTest extends WebTestCase
{
    public function testApiCannotUpvoteCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/1");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotUpvoteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/1", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUpvoteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/1", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame(1, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        self::assertSame(1, $jsonData['userVote']);
        self::assertFalse($jsonData['isFavourited']);
    }

    public function testApiCannotDownvoteCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/-1");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotDownvoteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/-1", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanDownvoteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/-1", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(1, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        self::assertSame(-1, $jsonData['userVote']);
        self::assertFalse($jsonData['isFavourited']);
    }

    public function testApiCannotRemoveVoteCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $voteManager = $this->getService(VoteManager::class);
        $voteManager->vote(1, $comment, $this->getUserByUsername('user'), rateLimit: false);

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/0");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotRemoveVoteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $voteManager = $this->getService(VoteManager::class);
        $voteManager->vote(1, $comment, $user, rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/0", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanRemoveVoteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $voteManager = $this->getService(VoteManager::class);
        $voteManager->vote(1, $comment, $user, rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/vote/0", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        self::assertSame(0, $jsonData['userVote']);
        self::assertFalse($jsonData['isFavourited']);
    }

    public function testApiCannotFavouriteCommentAnonymous(): void
    {
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry);

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/favourite");

        self::assertResponseStatusCodeSame(401);
    }

    public function testApiCannotFavouriteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/favourite", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanFavouriteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/favourite", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(1, $jsonData['favourites']);
        self::assertSame(0, $jsonData['userVote']);
        self::assertTrue($jsonData['isFavourited']);
    }

    public function testApiCannotUnfavouriteCommentWithoutScope(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $favouriteManager = $this->getService(FavouriteManager::class);
        $favouriteManager->toggle($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/favourite", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testApiCanUnfavouriteComment(): void
    {
        $user = $this->getUserByUsername('user');
        $entry = $this->getEntryByTitle('an entry', body: 'test');
        $comment = $this->createEntryComment('test comment', $entry, $user);

        $favouriteManager = $this->getService(FavouriteManager::class);
        $favouriteManager->toggle($user, $comment);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read entry_comment:vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('PUT', "/api/comments/{$comment->getId()}/favourite", server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseStatusCodeSame(200);
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData);
        self::assertSame(0, $jsonData['uv']);
        self::assertSame(0, $jsonData['dv']);
        self::assertSame(0, $jsonData['favourites']);
        self::assertSame(0, $jsonData['userVote']);
        self::assertFalse($jsonData['isFavourited']);
    }
}

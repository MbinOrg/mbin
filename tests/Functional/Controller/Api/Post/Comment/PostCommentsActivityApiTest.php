<?php

namespace App\Tests\Functional\Controller\Api\Post\Comment;

use App\DTO\UserSmallResponseDto;
use App\Tests\Functional\Controller\Api\Entry\EntriesActivityApiTest;
use App\Tests\WebTestCase;

class PostCommentsActivityApiTest extends WebTestCase
{

    public function testEmpty() {
        $user = $this->getUserByUsername('user');
        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $user, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $user);

        $this->client->jsonRequest('GET', "/api/post-comments/{$comment->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(EntriesActivityApiTest::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['boosts']);
        self::assertSame([], $jsonData['upvotes']);
        self::assertSame(null, $jsonData['downvotes']);
    }

    public function testUpvotes() {
        $author = $this->getUserByUsername('userA');
        $user1 = $this->getUserByUsername('user1');
        $user2 = $this->getUserByUsername('user2');
        $this->getUserByUsername('user3');

        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $author, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $author);

        $this->favouriteManager->toggle($user1, $comment);
        $this->favouriteManager->toggle($user2, $comment);

        $this->client->jsonRequest('GET', "/api/post-comments/{$comment->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(EntriesActivityApiTest::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['boosts']);
        self::assertSame(null, $jsonData['downvotes']);

        self::assertCount(2, $jsonData['upvotes']);
        self::assertTrue(\array_all($jsonData['upvotes'], function ($u) use ($user1, $user2) {
            /* @var UserSmallResponseDto $u */
            return $u['userId'] === $user1->getId() || $u['userId'] === $user2->getId();
        }), \serialize($jsonData['upvotes']));
    }

    public function testBoosts() {
        $author = $this->getUserByUsername('userA');
        $user1 = $this->getUserByUsername('user1');
        $user2 = $this->getUserByUsername('user2');
        $this->getUserByUsername('user3');

        $magazine = $this->getMagazineByNameNoRSAKey('acme');
        $post = $this->createPost('test post', user: $author, magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post, $author);

        $this->voteManager->upvote($comment, $user1);
        $this->voteManager->upvote($comment, $user2);

        $this->client->jsonRequest('GET', "/api/post-comments/{$comment->getId()}/activity");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(EntriesActivityApiTest::ACTIVITIES_RESPONSE_DTO_KEYS, $jsonData);
        self::assertSame([], $jsonData['upvotes']);
        self::assertSame(null, $jsonData['downvotes']);

        self::assertCount(2, $jsonData['boosts']);
        self::assertTrue(\array_all($jsonData['boosts'], function ($u) use ($user1, $user2) {
            /* @var UserSmallResponseDto $u */
            return $u['userId'] === $user1->getId() || $u['userId'] === $user2->getId();
        }), \serialize($jsonData['boosts']));
    }
}

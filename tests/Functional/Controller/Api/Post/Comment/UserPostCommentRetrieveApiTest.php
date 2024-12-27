<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Post\Comment;

use App\Tests\WebTestCase;

class UserPostCommentRetrieveApiTest extends WebTestCase
{
    public function testApiCanGetUserPostCommentsAnonymous(): void
    {
        $this->createPost('a post');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $post = $this->createPost('another post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);
        $user = $post->user;

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments");
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($post->getId(), $jsonData['items'][0]['postId']);
    }

    public function testApiCanGetUserPostComments(): void
    {
        $this->createPost('a post');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $post = $this->createPost('another post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);
        $user = $post->user;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($post->getId(), $jsonData['items'][0]['postId']);
    }

    public function testApiCanGetUserPostCommentsDepth(): void
    {
        $this->createPost('a post');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $post = $this->createPost('another post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);
        $nested1 = $this->createPostComment('test comment nested 1', $post, parent: $comment);
        $nested2 = $this->createPostComment('test comment nested 2', $post, parent: $nested1);
        $nested3 = $this->createPostComment('test comment nested 3', $post, parent: $nested2);
        $user = $post->user;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments?d=2", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(4, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(4, $jsonData['pagination']['count']);

        foreach ($jsonData['items'] as $comment) {
            self::assertIsArray($comment);
            self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $comment);
            self::assertTrue(\count($comment['children']) <= 1);
            $depth = 0;
            $current = $comment;
            while (\count($current['children']) > 0) {
                ++$depth;
                $current = $current['children'][0];
                self::assertIsArray($current);
                self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $current);
            }
            self::assertTrue($depth <= 2);
        }
    }

    public function testApiCanGetUserPostCommentsNewest(): void
    {
        $post = $this->createPost('post');
        $first = $this->createPostComment('first', $post);
        $second = $this->createPostComment('second', $post);
        $third = $this->createPostComment('third', $post);
        $user = $post->user;

        $first->createdAt = new \DateTimeImmutable('-1 hour');
        $second->createdAt = new \DateTimeImmutable('-1 second');
        $third->createdAt = new \DateTimeImmutable();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments?sort=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($third->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($first->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetUserPostCommentsOldest(): void
    {
        $post = $this->createPost('post');
        $first = $this->createPostComment('first', $post);
        $second = $this->createPostComment('second', $post);
        $third = $this->createPostComment('third', $post);
        $user = $post->user;

        $first->createdAt = new \DateTimeImmutable('-1 hour');
        $second->createdAt = new \DateTimeImmutable('-1 second');
        $third->createdAt = new \DateTimeImmutable();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments?sort=oldest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($first->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($third->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetUserPostCommentsActive(): void
    {
        $post = $this->createPost('post');
        $first = $this->createPostComment('first', $post);
        $second = $this->createPostComment('second', $post);
        $third = $this->createPostComment('third', $post);
        $user = $post->user;

        $first->lastActive = new \DateTime('-1 hour');
        $second->lastActive = new \DateTime('-1 second');
        $third->lastActive = new \DateTime();

        $entityManager = $this->entityManager;
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);
        $entityManager->flush();

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments?sort=active", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($third->getId(), $jsonData['items'][0]['commentId']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($first->getId(), $jsonData['items'][2]['commentId']);
    }

    public function testApiCanGetUserPostCommentsHot(): void
    {
        $post = $this->createPost('post');
        $first = $this->createPostComment('first', $post);
        $second = $this->createPostComment('second', $post);
        $third = $this->createPostComment('third', $post);
        $user = $post->user;

        $voteManager = $this->voteManager;
        $voteManager->vote(1, $first, $this->getUserByUsername('voter1'), rateLimit: false);
        $voteManager->vote(1, $first, $this->getUserByUsername('voter2'), rateLimit: false);
        $voteManager->vote(1, $second, $this->getUserByUsername('voter1'), rateLimit: false);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments?sort=hot", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(3, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($first->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame(2, $jsonData['items'][0]['uv']);

        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($second->getId(), $jsonData['items'][1]['commentId']);
        self::assertSame(1, $jsonData['items'][1]['uv']);

        self::assertIsArray($jsonData['items'][2]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][2]);
        self::assertSame($third->getId(), $jsonData['items'][2]['commentId']);
        self::assertSame(0, $jsonData['items'][2]['uv']);
    }

    public function testApiCanGetUserPostCommentsWithUserVoteStatus(): void
    {
        $this->createPost('a post');
        $magazine = $this->getMagazineByNameNoRSAKey('somemag');
        $post = $this->createPost('another post', magazine: $magazine);
        $comment = $this->createPostComment('test comment', $post);
        $user = $post->user;

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->getUserByUsername('user'));

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read vote');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', "/api/users/{$user->getId()}/post-comments", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($comment->getId(), $jsonData['items'][0]['commentId']);
        self::assertSame($post->getId(), $jsonData['items'][0]['postId']);
        self::assertEquals('test comment', $jsonData['items'][0]['body']);
        self::assertIsArray($jsonData['items'][0]['magazine']);
        self::assertArrayKeysMatch(self::MAGAZINE_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['magazine']);
        self::assertSame($magazine->getId(), $jsonData['items'][0]['magazine']['magazineId']);
        self::assertIsArray($jsonData['items'][0]['user']);
        self::assertArrayKeysMatch(self::USER_SMALL_RESPONSE_KEYS, $jsonData['items'][0]['user']);
        self::assertNull($jsonData['items'][0]['image']);
        self::assertEquals('en', $jsonData['items'][0]['lang']);
        self::assertEmpty($jsonData['items'][0]['tags']);
        self::assertSame(0, $jsonData['items'][0]['childCount']);
        self::assertIsArray($jsonData['items'][0]['children']);
        self::assertEmpty($jsonData['items'][0]['children']);
        self::assertSame(0, $jsonData['items'][0]['uv']);
        self::assertSame(0, $jsonData['items'][0]['dv']);
        self::assertSame(0, $jsonData['items'][0]['favourites']);
        self::assertFalse($jsonData['items'][0]['isFavourited']);
        self::assertSame(0, $jsonData['items'][0]['userVote']);
        self::assertFalse($jsonData['items'][0]['isAdult']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['items'][0]['createdAt'], 'createdAt date format invalid');
        self::assertNull($jsonData['items'][0]['editedAt']);
        self::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d%i:00', $jsonData['items'][0]['lastActive'], 'lastActive date format invalid');
        self::assertNull($jsonData['items'][0]['apId']);
    }
}

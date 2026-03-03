<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Combined;

use App\Entity\Magazine;
use App\Entity\User;
use App\Tests\WebTestCase;

use function PHPUnit\Framework\assertEquals;

class CombinedRetrieveApiTest extends WebTestCase
{
    private Magazine $magazine;
    private User $user;
    private array $generatedEntries = [];
    private array $generatedPosts = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->magazine = $this->getMagazineByName('acme');
        $this->user = $this->getUserByUsername('user');
        $this->magazineManager->subscribe($this->magazine, $this->user);
        for ($i = 0; $i < 10; ++$i) {
            $entry = $this->getEntryByTitle("Test Entry $i", magazine: $this->magazine);
            $entry->createdAt = new \DateTimeImmutable("now - $i minutes");
            $this->entityManager->persist($entry);
            $this->generatedEntries[] = $entry;
            ++$i;
            $post = $this->createPost("Test Post $i", magazine: $this->magazine);
            $post->createdAt = new \DateTimeImmutable("now - $i minutes");
            $this->entityManager->persist($post);
            $this->generatedPosts[] = $post;
        }
        $this->entityManager->flush();
    }

    public function testApiCanGetSubscribedContentWithBoosts(): void
    {
        $user = $this->getUserByUsername('user');
        $userFollowing = $this->getUserByUsername('user2');
        $user3 = $this->getUserByUsername('user3');
        $magazine = $this->getMagazineByName('abc');

        $this->userManager->follow($user, $userFollowing, false);

        $postFollowed = $this->createPost('a post', user: $userFollowing);
        $postBoosted = $this->createPost('third user post', user: $user3);
        $this->createPost('unrelated post', user: $user3);
        $postCommentFollowed = $this->createPostComment('a comment', $postBoosted, $userFollowing);
        $postCommentBoosted = $this->createPostComment('a boosted comment', $postBoosted, $user3);
        $this->createPostComment('unrelated comment', $postBoosted, $user3);
        $entryFollowed = $this->createEntry('title', $magazine, body: 'an entry', user: $userFollowing);
        $entryBoosted = $this->createEntry('title', $magazine, body: 'third user post', user: $user3);
        $this->createEntry('title', $magazine, body: 'unrelated post', user: $user3);
        $entryCommentFollowed = $this->createEntryComment('a comment', $entryBoosted, $userFollowing);
        $entryCommentBoosted = $this->createEntryComment('a boosted comment', $entryBoosted, $user3);
        $this->createEntryComment('unrelated comment', $entryBoosted, $user3);

        $this->voteManager->upvote($postBoosted, $userFollowing);
        $this->voteManager->upvote($postCommentBoosted, $userFollowing);
        $this->voteManager->upvote($entryBoosted, $userFollowing);
        $this->voteManager->upvote($entryCommentBoosted, $userFollowing);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/combined/subscribed?includeBoosts=true', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(8, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(8, $jsonData['pagination']['count']);

        $retrievedPostIds = array_map(function ($item) {
            if (null !== $item['post']) {
                self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $item['post']);

                return $item['post']['postId'];
            } else {
                return null;
            }
        }, $jsonData['items']);
        $retrievedPostIds = array_filter($retrievedPostIds, function ($item) { return null !== $item; });
        sort($retrievedPostIds);

        $retrievedPostCommentIds = array_map(function ($item) {
            if (null !== $item['postComment']) {
                self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $item['postComment']);

                return $item['postComment']['commentId'];
            } else {
                return null;
            }
        }, $jsonData['items']);
        $retrievedPostCommentIds = array_filter($retrievedPostCommentIds, function ($item) { return null !== $item; });
        sort($retrievedPostCommentIds);

        $retrievedEntryIds = array_map(function ($item) {
            if (null !== $item['entry']) {
                self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $item['entry']);

                return $item['entry']['entryId'];
            } else {
                return null;
            }
        }, $jsonData['items']);
        $retrievedEntryIds = array_filter($retrievedEntryIds, function ($item) { return null !== $item; });
        sort($retrievedEntryIds);

        $retrievedEntryCommentIds = array_map(function ($item) {
            if (null !== $item['entryComment']) {
                self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $item['entryComment']);

                return $item['entryComment']['commentId'];
            } else {
                return null;
            }
        }, $jsonData['items']);
        $retrievedEntryCommentIds = array_filter($retrievedEntryCommentIds, function ($item) { return null !== $item; });
        sort($retrievedEntryCommentIds);

        $expectedPostIds = [$postFollowed->getId(), $postBoosted->getId()];
        sort($expectedPostIds);
        $expectedPostCommentIds = [$postCommentFollowed->getId(), $postCommentBoosted->getId()];
        sort($expectedPostCommentIds);
        $expectedEntryIds = [$entryFollowed->getId(), $entryBoosted->getId()];
        sort($expectedEntryIds);
        $expectedEntryCommentIds = [$entryCommentFollowed->getId(), $entryCommentBoosted->getId()];
        sort($expectedEntryCommentIds);
        self::assertEquals($retrievedPostIds, $expectedPostIds);
        self::assertEquals($expectedPostCommentIds, $expectedPostCommentIds);
        self::assertEquals($expectedEntryIds, $retrievedEntryIds);
        self::assertEquals($expectedEntryCommentIds, $retrievedEntryCommentIds);
    }

    public function testApiHonersIncludeBoostsUserSetting(): void
    {
        $user = $this->getUserByUsername('user');
        $userFollowing = $this->getUserByUsername('user2');
        $user3 = $this->getUserByUsername('user3');

        $this->userManager->follow($user, $userFollowing, false);

        $this->createPost('a post', user: $userFollowing);
        $postBoosted = $this->createPost('third user post', user: $user3);

        $this->voteManager->upvote($postBoosted, $userFollowing);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/combined/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(1, $jsonData['pagination']['count']);

        $this->userRepository->find($user->getId())->showBoostsOfFollowing = true;
        $this->entityManager->flush();

        $this->client->request('GET', '/api/combined/subscribed', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);
    }

    public function testCombinedAnonymous(): void
    {
        $this->client->request('GET', '/api/combined?perPage=2&content=all&sort=newest');

        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        self::assertArrayKeysMatch(WebTestCase::PAGINATED_KEYS, $data);
        self::assertCount(2, $data['items']);
        self::assertArrayKeysMatch(WebTestCase::PAGINATION_KEYS, $data['pagination']);
        self::assertEquals(5, $data['pagination']['maxPage']);

        self::assertArrayKeysMatch(WebTestCase::ENTRY_RESPONSE_KEYS, $data['items'][0]['entry']);
        self::assertNull($data['items'][0]['post']);
        assertEquals($this->generatedEntries[0]->getId(), $data['items'][0]['entry']['entryId']);
        self::assertArrayKeysMatch(WebTestCase::POST_RESPONSE_KEYS, $data['items'][1]['post']);
        self::assertNull($data['items'][1]['entry']);
        assertEquals($this->generatedPosts[0]->getId(), $data['items'][1]['post']['postId']);
    }

    public function testCombinedCursoredAnonymous(): void
    {
        $this->client->request('GET', '/api/combined/2.0?perPage=2&sort=newest');

        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        $this->assertCursorDataShape($data);
    }

    public function testUserCombinedCursored(): void
    {
        $this->client->loginUser($this->user);
        self::createOAuth2PublicAuthCodeClient();
        $codes = self::getPublicAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];
        $this->client->request('GET', '/api/combined/2.0/subscribed?perPage=2&sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);

        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        $this->assertCursorDataShape($data);
    }

    private function assertCursorDataShape(array $data): void
    {
        self::assertArrayKeysMatch(WebTestCase::PAGINATED_KEYS, $data);

        self::assertCount(2, $data['items']);
        self::assertArrayKeysMatch(WebTestCase::CURSOR_PAGINATION_KEYS, $data['pagination']);
        self::assertNotNull($data['pagination']['nextCursor']);
        self::assertNotNull($data['pagination']['currentCursor']);
        self::assertNull($data['pagination']['previousCursor']);

        self::assertArrayKeysMatch(WebTestCase::ENTRY_RESPONSE_KEYS, $data['items'][0]['entry']);
        self::assertNull($data['items'][0]['post']);
        assertEquals($this->generatedEntries[0]->getId(), $data['items'][0]['entry']['entryId']);
        self::assertArrayKeysMatch(WebTestCase::POST_RESPONSE_KEYS, $data['items'][1]['post']);
        self::assertNull($data['items'][1]['entry']);
        assertEquals($this->generatedPosts[0]->getId(), $data['items'][1]['post']['postId']);

        $this->client->request('GET', '/api/combined/2.0?perPage=2&sort=newest&cursor=' . urlencode($data['pagination']['nextCursor']));
        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);

        self::assertCount(2, $data['items']);
        self::assertArrayKeysMatch(WebTestCase::CURSOR_PAGINATION_KEYS, $data['pagination']);
        self::assertNotNull($data['pagination']['nextCursor']);
        self::assertNotNull($data['pagination']['currentCursor']);
        self::assertNotNull($data['pagination']['previousCursor']);

        self::assertArrayKeysMatch(WebTestCase::ENTRY_RESPONSE_KEYS, $data['items'][0]['entry']);
        self::assertNull($data['items'][0]['post']);
        assertEquals($this->generatedEntries[1]->getId(), $data['items'][0]['entry']['entryId']);
        self::assertArrayKeysMatch(WebTestCase::POST_RESPONSE_KEYS, $data['items'][1]['post']);
        self::assertNull($data['items'][1]['entry']);
        assertEquals($this->generatedPosts[1]->getId(), $data['items'][1]['post']['postId']);
    }
}

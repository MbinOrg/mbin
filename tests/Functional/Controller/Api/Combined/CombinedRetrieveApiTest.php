<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Combined;

use App\Tests\WebTestCase;

class CombinedRetrieveApiTest extends WebTestCase
{
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
}

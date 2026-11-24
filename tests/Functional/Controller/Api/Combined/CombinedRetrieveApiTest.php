<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Combined;

use App\Tests\WebTestCase;

class CombinedRetrieveApiTest extends WebTestCase
{
    public function testApiCanGetSubscribedContentWithBoosts(): void
    {
        $user = $this->getUserByUsername('user');
        $userFollowing1 = $this->getUserByUsername('user2');
        $userFollowing2 = $this->getUserByUsername('user3');
        $user3 = $this->getUserByUsername('user4');
        $magazine = $this->getMagazineByName('abc');

        $this->userManager->follow($user, $userFollowing1, false);
        $this->userManager->follow($user, $userFollowing2, false);

        $postFollowed = $this->createPost('a post', user: $userFollowing1);
        $postBoosted1 = $this->createPost('third user post 1', user: $user3);
        $postBoosted2 = $this->createPost('third user post 2', user: $user3);
        $this->createPost('unrelated post', user: $user3);
        $postCommentFollowed = $this->createPostComment('a comment', $postBoosted1, $userFollowing1);
        $postCommentBoosted = $this->createPostComment('a boosted comment', $postBoosted1, $user3);
        $this->createPostComment('unrelated comment', $postBoosted1, $user3);
        $entryFollowed = $this->createEntry('title', $magazine, body: 'an entry', user: $userFollowing1);
        $entryBoosted = $this->createEntry('title', $magazine, body: 'third user post', user: $user3);
        $this->createEntry('title', $magazine, body: 'unrelated post', user: $user3);
        $entryCommentFollowed = $this->createEntryComment('a comment', $entryBoosted, $userFollowing1);
        $entryCommentBoosted = $this->createEntryComment('a boosted comment', $entryBoosted, $user3);
        $this->createEntryComment('unrelated comment', $entryBoosted, $user3);

        $this->voteManager->upvote($postBoosted1, $userFollowing1);
        $this->voteManager->upvote($postBoosted2, $userFollowing1);
        sleep(1);
        $this->voteManager->upvote($postBoosted2, $userFollowing2);
        $this->voteManager->upvote($postCommentBoosted, $userFollowing1);
        $this->voteManager->upvote($entryBoosted, $userFollowing1);
        $this->voteManager->upvote($entryCommentBoosted, $userFollowing1);

        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($user);

        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'read');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->request('GET', '/api/combined/subscribed?includeBoosts=true&sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(9, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(9, $jsonData['pagination']['count']);

        $boostedContentIds = [
            'post' => [$postBoosted1->getId(), $postBoosted2->getId()],
            'postComment' => [$postCommentBoosted->getId()],
            'entry' => [$entryBoosted->getId()],
            'entryComment' => [$entryCommentBoosted->getId()],
        ];
        $boostedContentSeen = [];
        foreach ($jsonData['items'] as $item) {
            foreach ($boostedContentIds as $type => $ids) {
                foreach ($ids as $id) {
                    $idKey = str_ends_with($type, 'Comment') ? 'commentId' : $type.'Id';
                    if (($item[$type][$idKey] ?? null) === $id) {
                        if ('post' === $type && $item['post']['postId'] === $postBoosted2->getId()) {
                            self::assertCount(2, $item['boostedBy']);
                            self::assertSame($userFollowing1->getId(), $item['boostedBy'][0]['user']['userId']);
                            self::assertSame($userFollowing1->username, $item['boostedBy'][0]['user']['username']);
                            self::assertSame($userFollowing2->getId(), $item['boostedBy'][1]['user']['userId']);
                            self::assertSame($userFollowing2->username, $item['boostedBy'][1]['user']['username']);
                        } else {
                            self::assertCount(1, $item['boostedBy']);
                            self::assertSame($userFollowing1->getId(), $item['boostedBy'][0]['user']['userId']);
                            self::assertSame($userFollowing1->username, $item['boostedBy'][0]['user']['username']);
                        }
                        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $item['boostedBy'][0]['boostedAt']));
                        $boostedContentSeen[] = $type;
                    }
                }
            }
        }
        self::assertEqualsCanonicalizing(['post', 'post', 'entry', 'entryComment', 'postComment'], $boostedContentSeen);

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

        $expectedPostIds = [$postFollowed->getId(), $postBoosted1->getId(), $postBoosted2->getId()];
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

        $this->client->request('GET', '/api/combined/subscribed?sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);
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

        $this->client->request('GET', '/api/combined/subscribed?sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);
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

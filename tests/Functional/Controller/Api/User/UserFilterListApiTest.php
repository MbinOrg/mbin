<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\User;

use App\Entity\User;
use App\Entity\UserFilterList;
use App\Tests\WebTestCase;

class UserFilterListApiTest extends WebTestCase
{
    public const array USER_FILTER_LIST_KEYS = [
        'id',
        'name',
        'expirationDate',
        'feeds',
        'comments',
        'profile',
        'words',
    ];

    private User $listUser;

    private User $otherUser;

    private UserFilterList $list;

    public function testUserRetrieve(): void
    {
        $token = $this->getListUserToken();
        $this->client->request('GET', '/api/users/filterLists', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $data = self::getJsonResponse($this->client);
        self::assertArrayHasKey('items', $data);
        self::assertCount(1, $data['items']);
        $list = $data['items'][0];
        self::assertArrayKeysMatch(self::USER_FILTER_LIST_KEYS, $list);
    }

    public function testAnonymousCannotRetrieve(): void
    {
        $this->client->request('GET', '/api/users/filterLists');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUserCanEditList(): void
    {
        $token = $this->getListUserToken();
        $requestParams = [
            'name' => 'Some new Name',
            'expirationDate' => (new \DateTimeImmutable('now - 5 days'))->format(DATE_ATOM),
            'feeds' => false,
            'profile' => false,
            'comments' => false,
            'words' => [
                [
                    'exactMatch' => true,
                    'word' => 'newWord',
                ],
                [
                    'exactMatch' => false,
                    'word' => 'sOmEnEwWoRd',
                ],
            ],
        ];

        $this->client->jsonRequest('PUT', '/api/users/filterLists/'.$this->list->getId(), $requestParams, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $data = self::getJsonResponse($this->client);
        self::assertArrayIsEqualToArrayIgnoringListOfKeys($requestParams, $data, ['id']);
    }

    public function testOtherUserCannotEditList(): void
    {
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->otherUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];
        $requestParams = [
            'name' => 'Some new Name',
            'expirationDate' => null,
            'feeds' => false,
            'profile' => false,
            'comments' => false,
            'words' => $this->list->words,
        ];

        $this->client->jsonRequest('PUT', '/api/users/filterLists/'.$this->list->getId(), $requestParams, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUserCanDeleteList(): void
    {
        $token = $this->getListUserToken();

        $this->client->jsonRequest('DELETE', '/api/users/filterLists/'.$this->list->getId(), server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $freshList = $this->entityManager->getRepository(UserFilterList::class)->find($this->list->getId());
        self::assertNull($freshList);
    }

    public function testOtherUserCannotDeleteList(): void
    {
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->otherUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        $this->client->jsonRequest('DELETE', '/api/users/filterLists/'.$this->list->getId(), server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseStatusCodeSame(403);

        $freshList = $this->entityManager->getRepository(UserFilterList::class)->find($this->list->getId());
        self::assertNotNull($freshList);
    }

    public function testFilteredHomePage(): void
    {
        $token = $this->getListUserToken();

        $this->deactivateFilterList($token);

        $entry = $this->getEntryByTitle('Cringe entry', body: 'some entry');
        $entry2 = $this->getEntryByTitle('Some entry', body: 'some entry');
        $entry2->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $entry3 = $this->getEntryByTitle('Some other entry', body: 'some entry with a cringe body');
        $entry3->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $post = $this->createPost('Cringe body');
        $post->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $post2 = $this->createPost('Body with a cringe text');
        $post2->createdAt = new \DateTimeImmutable('now - 4 minutes');
        $post3 = $this->createPost('Some post');
        $post3->createdAt = new \DateTimeImmutable('now - 5 minutes');
        $this->entityManager->flush();

        $this->client->jsonRequest('GET', '/api/combined?sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $data);

        self::assertIsArray($data['items']);
        self::assertCount(6, $data['items']);
        self::assertEquals($entry->getId(), $data['items'][0]['entry']['entryId']);
        self::assertEquals($entry2->getId(), $data['items'][1]['entry']['entryId']);
        self::assertEquals($entry3->getId(), $data['items'][2]['entry']['entryId']);
        self::assertEquals($post->getId(), $data['items'][3]['post']['postId']);
        self::assertEquals($post2->getId(), $data['items'][4]['post']['postId']);
        self::assertEquals($post3->getId(), $data['items'][5]['post']['postId']);

        // activate list
        $this->activateFilterList($token);

        $this->client->jsonRequest('GET', '/api/combined?sortBy=newest', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $data);

        self::assertIsArray($data['items']);
        self::assertCount(2, $data['items']);
        self::assertEquals($entry2->getId(), $data['items'][0]['entry']['entryId']);
        self::assertEquals($post3->getId(), $data['items'][1]['post']['postId']);
    }

    public function testFilteredHomePageExact(): void
    {
        $token = $this->getListUserToken();
        $this->deactivateFilterList($token);

        $entry = $this->getEntryByTitle('TEST entry', body: 'some entry');
        $entry2 = $this->getEntryByTitle('Some entry', body: 'some test entry');
        $entry2->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $entry3 = $this->getEntryByTitle('Some other entry', body: 'some entry with a TEST body');
        $entry3->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $post = $this->createPost('TEST body');
        $post->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $post2 = $this->createPost('Body with a TEST text');
        $post2->createdAt = new \DateTimeImmutable('now - 4 minutes');
        $post3 = $this->createPost('Some test post');
        $post3->createdAt = new \DateTimeImmutable('now - 5 minutes');
        $this->entityManager->flush();

        $this->client->jsonRequest('GET', '/api/combined?sort=newest', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $data);

        self::assertIsArray($data['items']);
        self::assertCount(6, $data['items']);
        self::assertEquals($entry->getId(), $data['items'][0]['entry']['entryId']);
        self::assertEquals($entry2->getId(), $data['items'][1]['entry']['entryId']);
        self::assertEquals($entry3->getId(), $data['items'][2]['entry']['entryId']);
        self::assertEquals($post->getId(), $data['items'][3]['post']['postId']);
        self::assertEquals($post2->getId(), $data['items'][4]['post']['postId']);
        self::assertEquals($post3->getId(), $data['items'][5]['post']['postId']);

        $this->activateFilterList($token);

        $this->client->jsonRequest('GET', '/api/combined?sortBy=newest', server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $data = self::getJsonResponse($this->client);
        self::assertIsArray($data);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $data);

        self::assertIsArray($data['items']);
        self::assertCount(2, $data['items']);
        self::assertEquals($entry2->getId(), $data['items'][0]['entry']['entryId']);
        self::assertEquals($post3->getId(), $data['items'][1]['post']['postId']);
    }

    public function testFilteredEntryComments(): void
    {
        $token = $this->getListUserToken();

        $entry = $this->getEntryByTitle('Some Entry');
        $comment1 = $this->createEntryComment('Some normal comment', $entry);
        $comment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment1 = $this->createEntryComment('Some sub comment', $entry, parent: $comment1);
        $subComment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment2 = $this->createEntryComment('Some Cringe sub comment', $entry, parent: $comment1);
        $subComment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $subComment3 = $this->createEntryComment('Some other Cringe sub comment', $entry, parent: $comment1);
        $subComment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $comment2 = $this->createEntryComment('Some cringe comment', $entry);
        $comment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $comment3 = $this->createEntryComment('Some other Cringe comment', $entry);
        $comment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->request('GET', "/api/entry/{$entry->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertEquals($comment2->getId(), $jsonData['items'][1]['commentId']);
        self::assertEquals($comment3->getId(), $jsonData['items'][2]['commentId']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment2->getId(), $jsonData['items'][0]['children'][1]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][2]['commentId']);

        $this->activateFilterList($token);

        $this->client->request('GET', "/api/entry/{$entry->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertCount(1, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
    }

    public function testFilteredEntryCommentsExact(): void
    {
        $token = $this->getListUserToken();

        $entry = $this->getEntryByTitle('Some Entry');
        $comment1 = $this->createEntryComment('Some normal comment', $entry);
        $comment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment1 = $this->createEntryComment('Some sub comment', $entry, parent: $comment1);
        $subComment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment2 = $this->createEntryComment('Some TEST sub comment', $entry, parent: $comment1);
        $subComment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $subComment3 = $this->createEntryComment('Some other test sub comment', $entry, parent: $comment1);
        $subComment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $comment2 = $this->createEntryComment('Some TEST comment', $entry);
        $comment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $comment3 = $this->createEntryComment('Some other test comment', $entry);
        $comment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->request('GET', "/api/entry/{$entry->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertEquals($comment2->getId(), $jsonData['items'][1]['commentId']);
        self::assertEquals($comment3->getId(), $jsonData['items'][2]['commentId']);
        self::assertCount(3, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment2->getId(), $jsonData['items'][0]['children'][1]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][2]['commentId']);

        $this->activateFilterList($token);

        $this->client->request('GET', "/api/entry/{$entry->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertCount(2, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][1]['commentId']);
    }

    public function testFilteredPostComments(): void
    {
        $token = $this->getListUserToken();

        $post = $this->createPost('Some Post');
        $comment1 = $this->createPostComment('Some normal comment', $post);
        $comment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment1 = $this->createPostComment('Some sub comment', $post, parent: $comment1);
        $subComment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment2 = $this->createPostComment('Some Cringe sub comment', $post, parent: $comment1);
        $subComment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $subComment3 = $this->createPostComment('Some other Cringe sub comment', $post, parent: $comment1);
        $subComment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $comment2 = $this->createPostComment('Some cringe comment', $post);
        $comment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $comment3 = $this->createPostComment('Some other Cringe comment', $post);
        $comment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->request('GET', "/api/posts/{$post->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertEquals($comment2->getId(), $jsonData['items'][1]['commentId']);
        self::assertEquals($comment3->getId(), $jsonData['items'][2]['commentId']);
        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment2->getId(), $jsonData['items'][0]['children'][1]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][2]['commentId']);

        $this->activateFilterList($token);

        $this->client->request('GET', "/api/posts/{$post->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(1, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertCount(1, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
    }

    public function testFilteredPostCommentsExact(): void
    {
        $token = $this->getListUserToken();

        $post = $this->createPost('Some Post');
        $comment1 = $this->createPostComment('Some normal comment', $post);
        $comment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment1 = $this->createPostComment('Some sub comment', $post, parent: $comment1);
        $subComment1->createdAt = new \DateTimeImmutable('now - 1 minutes');
        $subComment2 = $this->createPostComment('Some TEST sub comment', $post, parent: $comment1);
        $subComment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $subComment3 = $this->createPostComment('Some other test sub comment', $post, parent: $comment1);
        $subComment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $comment2 = $this->createPostComment('Some TEST comment', $post);
        $comment2->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $comment3 = $this->createPostComment('Some other test comment', $post);
        $comment3->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->request('GET', "/api/posts/{$post->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(3, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertEquals($comment2->getId(), $jsonData['items'][1]['commentId']);
        self::assertEquals($comment3->getId(), $jsonData['items'][2]['commentId']);
        self::assertCount(3, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment2->getId(), $jsonData['items'][0]['children'][1]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][2]['commentId']);

        $this->activateFilterList($token);

        $this->client->request('GET', "/api/posts/{$post->getId()}/comments?sortBy=newest", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertEquals($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertCount(2, $jsonData['items'][0]['children']);
        self::assertEquals($subComment1->getId(), $jsonData['items'][0]['children'][0]['commentId']);
        self::assertEquals($subComment3->getId(), $jsonData['items'][0]['children'][1]['commentId']);
    }

    public function testFilteredProfile(): void
    {
        $token = $this->getListUserToken();
        $otherUser = $this->userRepository->findOneByUsername('otherUser');
        $magazine = $this->getMagazineByName('someMag');
        $entry = $this->createEntry('Some Entry', $magazine, $otherUser);
        $entry->createdAt = new \DateTimeImmutable('now - 10 minutes');
        $entryComment1 = $this->createEntryComment('Some comment', $entry, user: $otherUser);
        $entryComment1->createdAt = new \DateTimeImmutable('now - 9 minutes');
        $entryComment2 = $this->createEntryComment('Some cringe comment', $entry, user: $otherUser);
        $entryComment2->createdAt = new \DateTimeImmutable('now - 8 minutes');
        $entryComment3 = $this->createEntryComment('Some Cringe comment', $entry, user: $otherUser);
        $entryComment3->createdAt = new \DateTimeImmutable('now - 7 minutes');
        $entry2 = $this->getEntryByTitle('Some cringe Entry', user: $otherUser);
        $entry2->createdAt = new \DateTimeImmutable('now - 6 minutes');
        $post = $this->createPost('Some Post', user: $otherUser);
        $post->createdAt = new \DateTimeImmutable('now - 5 minutes');
        $postComment1 = $this->createPostComment('Some comment', $post, user: $otherUser);
        $postComment1->createdAt = new \DateTimeImmutable('now - 4 minutes');
        $postComment2 = $this->createPostComment('Some cringe comment', $post, user: $otherUser);
        $postComment2->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $postComment3 = $this->createPostComment('Some Cringe comment', $post, user: $otherUser);
        $postComment3->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $post2 = $this->createPost('Some Cringe Post', user: $otherUser);
        $post2->createdAt = new \DateTimeImmutable('now - 1 minutes');

        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->jsonRequest('GET', "/api/users/{$otherUser->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData['items']);
        self::assertCount(10, $jsonData['items']);
        self::assertEquals($entry->getId(), $jsonData['items'][9]['entry']['entryId']);
        self::assertEquals($entryComment1->getId(), $jsonData['items'][8]['entryComment']['commentId']);
        self::assertEquals($entryComment2->getId(), $jsonData['items'][7]['entryComment']['commentId']);
        self::assertEquals($entryComment3->getId(), $jsonData['items'][6]['entryComment']['commentId']);
        self::assertEquals($entry2->getId(), $jsonData['items'][5]['entry']['entryId']);
        self::assertEquals($post->getId(), $jsonData['items'][4]['post']['postId']);
        self::assertEquals($postComment1->getId(), $jsonData['items'][3]['postComment']['commentId']);
        self::assertEquals($postComment2->getId(), $jsonData['items'][2]['postComment']['commentId']);
        self::assertEquals($postComment3->getId(), $jsonData['items'][1]['postComment']['commentId']);
        self::assertEquals($post2->getId(), $jsonData['items'][0]['post']['postId']);

        $this->activateFilterList($token);

        $this->client->jsonRequest('GET', "/api/users/{$otherUser->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData['items']);
        self::assertCount(4, $jsonData['items']);
        self::assertEquals($entry->getId(), $jsonData['items'][3]['entry']['entryId']);
        self::assertEquals($entryComment1->getId(), $jsonData['items'][2]['entryComment']['commentId']);
        self::assertEquals($post->getId(), $jsonData['items'][1]['post']['postId']);
        self::assertEquals($postComment1->getId(), $jsonData['items'][0]['postComment']['commentId']);
    }

    public function testFilteredProfileExact(): void
    {
        $token = $this->getListUserToken();
        $otherUser = $this->userRepository->findOneByUsername('otherUser');
        $magazine = $this->getMagazineByName('someMag');
        $entry = $this->createEntry('Some Entry', $magazine, $otherUser);
        $entry->createdAt = new \DateTimeImmutable('now - 10 minutes');
        $entryComment1 = $this->createEntryComment('Some comment', $entry, user: $otherUser);
        $entryComment1->createdAt = new \DateTimeImmutable('now - 9 minutes');
        $entryComment2 = $this->createEntryComment('Some TEST comment', $entry, user: $otherUser);
        $entryComment2->createdAt = new \DateTimeImmutable('now - 8 minutes');
        $entryComment3 = $this->createEntryComment('Some test comment', $entry, user: $otherUser);
        $entryComment3->createdAt = new \DateTimeImmutable('now - 7 minutes');
        $entry2 = $this->getEntryByTitle('Some TEST Entry', user: $otherUser);
        $entry2->createdAt = new \DateTimeImmutable('now - 6 minutes');
        $post = $this->createPost('Some Post', user: $otherUser);
        $post->createdAt = new \DateTimeImmutable('now - 5 minutes');
        $postComment1 = $this->createPostComment('Some comment', $post, user: $otherUser);
        $postComment1->createdAt = new \DateTimeImmutable('now - 4 minutes');
        $postComment2 = $this->createPostComment('Some TEST comment', $post, user: $otherUser);
        $postComment2->createdAt = new \DateTimeImmutable('now - 3 minutes');
        $postComment3 = $this->createPostComment('Some test comment', $post, user: $otherUser);
        $postComment3->createdAt = new \DateTimeImmutable('now - 2 minutes');
        $post2 = $this->createPost('Some TEST Post', user: $otherUser);
        $post2->createdAt = new \DateTimeImmutable('now - 1 minutes');

        $this->entityManager->flush();

        $this->deactivateFilterList($token);

        $this->client->jsonRequest('GET', "/api/users/{$otherUser->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData['items']);
        self::assertCount(10, $jsonData['items']);
        self::assertEquals($entry->getId(), $jsonData['items'][9]['entry']['entryId']);
        self::assertEquals($entryComment1->getId(), $jsonData['items'][8]['entryComment']['commentId']);
        self::assertEquals($entryComment2->getId(), $jsonData['items'][7]['entryComment']['commentId']);
        self::assertEquals($entryComment3->getId(), $jsonData['items'][6]['entryComment']['commentId']);
        self::assertEquals($entry2->getId(), $jsonData['items'][5]['entry']['entryId']);
        self::assertEquals($post->getId(), $jsonData['items'][4]['post']['postId']);
        self::assertEquals($postComment1->getId(), $jsonData['items'][3]['postComment']['commentId']);
        self::assertEquals($postComment2->getId(), $jsonData['items'][2]['postComment']['commentId']);
        self::assertEquals($postComment3->getId(), $jsonData['items'][1]['postComment']['commentId']);
        self::assertEquals($post2->getId(), $jsonData['items'][0]['post']['postId']);

        $this->activateFilterList($token);

        $this->client->jsonRequest('GET', "/api/users/{$otherUser->getId()}/content", server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();

        $jsonData = self::getJsonResponse($this->client);
        self::assertIsArray($jsonData['items']);
        self::assertCount(6, $jsonData['items']);
        self::assertEquals($entry->getId(), $jsonData['items'][5]['entry']['entryId']);
        self::assertEquals($entryComment1->getId(), $jsonData['items'][4]['entryComment']['commentId']);
        self::assertEquals($entryComment3->getId(), $jsonData['items'][3]['entryComment']['commentId']);
        self::assertEquals($post->getId(), $jsonData['items'][2]['post']['postId']);
        self::assertEquals($postComment1->getId(), $jsonData['items'][1]['postComment']['commentId']);
        self::assertEquals($postComment3->getId(), $jsonData['items'][0]['postComment']['commentId']);
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->listUser = $this->getUserByUsername('listOwner');
        $this->otherUser = $this->getUserByUsername('otherUser');
        $this->list = new UserFilterList();
        $this->list->name = 'Test List';
        $this->list->user = $this->listUser;
        $this->list->expirationDate = null;
        $this->list->feeds = true;
        $this->list->profile = true;
        $this->list->comments = true;
        $this->list->words = [
            [
                'exactMatch' => true,
                'word' => 'TEST',
            ],
            [
                'exactMatch' => false,
                'word' => 'Cringe',
            ],
        ];
        $this->entityManager->persist($this->list);
        $this->entityManager->flush();
    }

    private function deactivateFilterList(string $token): void
    {
        $dto = $this->getFilterListDto();
        $this->client->jsonRequest('PUT', '/api/users/filterLists/'.$this->list->getId(), $dto, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
    }

    private function activateFilterList(string $token): void
    {
        $dto = $this->getFilterListDto();
        $dto['expirationDate'] = null;
        $this->client->jsonRequest('PUT', '/api/users/filterLists/'.$this->list->getId(), $dto, server: ['HTTP_AUTHORIZATION' => $token]);
        self::assertResponseIsSuccessful();
    }

    private function getFilterListDto(): array
    {
        return [
            'name' => $this->list->name,
            'expirationDate' => (new \DateTimeImmutable('now - 1 day'))->format(DATE_ATOM),
            'feeds' => true,
            'profile' => true,
            'comments' => true,
            'words' => $this->list->words,
        ];
    }

    private function getListUserToken(): string
    {
        self::createOAuth2AuthCodeClient();
        $this->client->loginUser($this->listUser);
        $codes = self::getAuthorizationCodeTokenResponse($this->client, scopes: 'user:profile:edit');
        $token = $codes['token_type'].' '.$codes['access_token'];

        return $token;
    }
}

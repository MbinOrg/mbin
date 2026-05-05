<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Tag;

use App\Tests\WebTestCase;

class TagContentRetrieveApiTest extends WebTestCase
{
    public function testApiCanListEntriesOfTag(): void
    {
        $this->getEntryByTitle('unrelated entry', body: 'text with #tag');
        $this->createPost('unrelated #testing post');
        $entry1 = $this->getEntryByTitle('entry 1', body: 'has tag #test');
        $entry2 = $this->getEntryByTitle('entry 2', body: 'has tag #test');

        $entry2->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/tag/test/entries?sort=newest');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($entry1->getId(), $jsonData['items'][0]['entryId']);
        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($entry2->getId(), $jsonData['items'][1]['entryId']);
    }

    public function testApiCanListEntryCommentsOfTag(): void
    {
        $this->getEntryByTitle('unrelated entry', body: 'text with #tag');
        $this->createPost('unrelated #testing post');
        $entry1 = $this->getEntryByTitle('entry 1', body: 'has comments');
        $entry2 = $this->getEntryByTitle('entry 2', body: 'has tag #test');
        $this->createEntryComment('comment', $entry1);
        $comment1 = $this->createEntryComment('comment 1 #test', $entry1);
        $comment2 = $this->createEntryComment('comment 2 #test', $entry2);

        $comment2->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/tag/test/entryComments?sort=newest');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::ENTRY_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($comment2->getId(), $jsonData['items'][1]['commentId']);
    }

    public function testApiCanListPostsOfTag(): void
    {
        $this->getEntryByTitle('unrelated entry', body: 'text with #tag');
        $this->createPost('unrelated #testing post');
        $post1 = $this->createPost('post 1 #test');
        $post2 = $this->createPost('#test post 2');

        $post2->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/tag/test/posts?sort=newest');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($post1->getId(), $jsonData['items'][0]['postId']);
        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($post2->getId(), $jsonData['items'][1]['postId']);
    }

    public function testApiCanListPostCommentsOfTag(): void
    {
        $this->getEntryByTitle('unrelated entry', body: 'text with #tag');
        $this->createPost('unrelated #testing post');
        $post1 = $this->createPost('has comments');
        $post2 = $this->createPost('has tag #test');
        $this->createPostComment('comment', $post1);
        $comment1 = $this->createPostComment('comment 1 #test', $post1);
        $comment2 = $this->createPostComment('comment 2 #test', $post2);

        $comment2->createdAt = new \DateTimeImmutable('now - 1min');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/tag/test/postComments?sort=newest');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['items']);
        self::assertCount(2, $jsonData['items']);
        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);
        self::assertSame(2, $jsonData['pagination']['count']);

        self::assertIsArray($jsonData['items'][0]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][0]);
        self::assertSame($comment1->getId(), $jsonData['items'][0]['commentId']);
        self::assertIsArray($jsonData['items'][1]);
        self::assertArrayKeysMatch(self::POST_COMMENT_RESPONSE_KEYS, $jsonData['items'][1]);
        self::assertSame($comment2->getId(), $jsonData['items'][1]['commentId']);
    }
}

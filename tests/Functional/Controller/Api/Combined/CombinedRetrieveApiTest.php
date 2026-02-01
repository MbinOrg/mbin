<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Combined;

use App\Entity\Magazine;
use App\Tests\WebTestCase;

use function PHPUnit\Framework\assertEquals;

class CombinedRetrieveApiTest extends WebTestCase
{
    private Magazine $magazine;
    private array $generatedEntries = [];
    private array $generatedPosts = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->magazine = $this->getMagazineByName('acme');
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

        $this->client->request('GET', '/api/combined/2.0?perPage=2&sort=newest&cursor='.urlencode($data['pagination']['nextCursor']));
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

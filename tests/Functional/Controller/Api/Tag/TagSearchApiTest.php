<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api\Tag;

use App\Tests\WebTestCase;

class TagSearchApiTest extends WebTestCase
{
    public function testApiCanListAllHashtags()
    {
        $this->loadExampleHashtags();

        // page 1
        $this->client->request('GET', '/api/tags/list');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $tags = $jsonData['items'];
        self::assertIsArray($tags);
        self::assertCount(25, $tags);

        $this->checkHashtagDto($tags[0], 'some_text');
        $this->checkHashtagDto($tags[1], 'test3');
        $this->checkHashtagDto($tags[2], 'test_1');
        $this->checkHashtagDto($tags[3], 'test_2');
        $this->checkHashtagDto($tags[4], 'thehashtag');
        for ($i = 6; $i <= 25; ++$i) {
            $num = str_pad(''.($i - 5), 2, '0', STR_PAD_LEFT);
            $this->checkHashtagDto($tags[$i - 1], 'z_forpagination_'.$num);
        }

        // page 2
        $this->client->request('GET', '/api/tags/list?p=2');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $tags = $jsonData['items'];
        self::assertIsArray($tags);
        self::assertCount(6, $tags);

        for ($i = 1; $i <= 6; ++$i) {
            $num = str_pad(''.($i + 20), 2, '0', STR_PAD_LEFT);
            $this->checkHashtagDto($tags[$i - 1], 'z_forpagination_'.$num);
        }
    }

    public function testApiCanSearchHashtags(): void
    {
        $this->loadExampleHashtags();

        // page 1
        $this->client->request('GET', '/api/tags/search?q=z_forpagination');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $tags = $jsonData['items'];
        self::assertIsArray($tags);
        self::assertCount(25, $tags);

        for ($i = 1; $i <= 25; ++$i) {
            $num = str_pad(''.$i, 2, '0', STR_PAD_LEFT);
            $this->checkHashtagDto($tags[$i - 1], 'z_forpagination_'.$num);
        }

        // page 2
        $this->client->request('GET', '/api/tags/search?q=z_forpagination&p=2');
        self::assertResponseIsSuccessful();
        $jsonData = self::getJsonResponse($this->client);

        self::assertIsArray($jsonData);
        self::assertArrayKeysMatch(self::PAGINATED_KEYS, $jsonData);

        self::assertIsArray($jsonData['pagination']);
        self::assertArrayKeysMatch(self::PAGINATION_KEYS, $jsonData['pagination']);

        $tags = $jsonData['items'];
        self::assertIsArray($tags);
        self::assertCount(1, $tags);
        $this->checkHashtagDto($tags[0], 'z_forpagination_26');
    }

    private function loadExampleHashtags(): void
    {
        $this->createHashtag('test_1');
        $this->createHashtag('test_2');
        $this->createHashtag('test3');
        $this->createHashtag('some_text');
        $this->createHashtag('thehashtag');

        for ($i = 1; $i <= 26; ++$i) {
            $this->createHashtag('z_forpagination_'.str_pad(''.$i, 2, '0', STR_PAD_LEFT));
        }
    }

    private function checkHashtagDto(array $tag, string $name): void
    {
        self::assertIsArray($tag);
        self::assertArrayKeysMatch(self::HASHTAG_RESPONSE_KEYS, $tag);
        self::assertSame(0, $tag['entryCount']);
        self::assertSame(0, $tag['entryCommentCount']);
        self::assertSame(0, $tag['postCount']);
        self::assertSame(0, $tag['postCommentCount']);
        self::assertNull($tag['isSubscribedByUser']);
        self::assertNull($tag['isBlockedByUser']);
        self::assertEquals($name, $tag['tag']);
    }
}

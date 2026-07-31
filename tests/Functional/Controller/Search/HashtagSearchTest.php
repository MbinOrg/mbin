<?php
declare(strict_types=1);

namespace App\Tests\Functional\Controller\Search;

use App\Tests\WebTestCase;

class HashtagSearchTest extends WebTestCase
{
    public function testListAllHashtags(): void
    {
        $this->loadExampleHashtags();

        $tagElementSelector = '.page-search #content .search-hashtags-list a';
        $tagElementIndexedSelector = '.page-search #content .search-hashtags-list li:nth-child($i) a';

        // page 1
        $crawler = $this->client->request('GET', '/search/hashtag');
        self::assertResponseIsSuccessful();
        $this->assertSelectorCount(25, $tagElementSelector);
        $this->assertSelectorTextSame(str_replace('$i', '1', $tagElementIndexedSelector), 'some_text');
        $this->assertSelectorTextSame(str_replace('$i', '2', $tagElementIndexedSelector), 'test3');
        $this->assertSelectorTextSame(str_replace('$i', '3', $tagElementIndexedSelector), 'test_1');
        $this->assertSelectorTextSame(str_replace('$i', '4', $tagElementIndexedSelector), 'test_2');
        $this->assertSelectorTextSame(str_replace('$i', '5', $tagElementIndexedSelector), 'thehashtag');
        for ($i = 6; $i <= 25; ++$i) {
            $num = str_pad(''.($i - 5), 2, '0', STR_PAD_LEFT);
            $this->assertSelectorTextSame(str_replace('$i', ''.$i, $tagElementIndexedSelector), 'z_forpagination_'.$num);
        }

        // page 2
        $nextPageUrl = $crawler->filter('.page-search #content .pagination .pagination__item--next-page')
            ->attr('href');
        self::assertSame('/search/hashtag?p=2', $nextPageUrl);
        $this->client->request('GET', $nextPageUrl);
        self::assertResponseIsSuccessful();
        $this->assertSelectorCount(5, $tagElementSelector);
        for ($i = 1; $i <= 5; ++$i) {
            $this->assertSelectorTextSame(str_replace('$i', ''.$i, $tagElementIndexedSelector), 'z_forpagination_'.($i + 20));
        }

        self::assertSelectorExists('.page-search #content .pagination .pagination__item--next-page.pagination__item--disabled');
    }

    public function testSearchHashtags(): void
    {
        $this->loadExampleHashtags();

        $tagElementSelector = '.page-search #content .search-hashtags-list a';
        $tagElementIndexedSelector = '.page-search #content .search-hashtags-list li:nth-child($i) a';

        // page 1
        $this->client->request('GET', '/search/hashtag?query=te');
        self::assertResponseIsSuccessful();
        $this->assertSelectorCount(4, $tagElementSelector);
        $this->assertSelectorTextSame(str_replace('$i', '1', $tagElementIndexedSelector), 'test3');
        $this->assertSelectorTextSame(str_replace('$i', '2', $tagElementIndexedSelector), 'test_1');
        $this->assertSelectorTextSame(str_replace('$i', '3', $tagElementIndexedSelector), 'test_2');
        $this->assertSelectorTextSame(str_replace('$i', '4', $tagElementIndexedSelector), 'some_text');

        self::assertSelectorNotExists('.page-search #content .pagination');
    }

    private function loadExampleHashtags(): void
    {
        $this->createHashtag('test_1');
        $this->createHashtag('test_2');
        $this->createHashtag('test3');
        $this->createHashtag('some_text');
        $this->createHashtag('thehashtag');

        for ($i = 1; $i <= 25; ++$i) {
            $this->createHashtag('z_forpagination_'.str_pad(''.$i, 2, '0', STR_PAD_LEFT));
        }
    }
}

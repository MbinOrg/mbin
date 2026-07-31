<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Search;

use App\Tests\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class MagazineSearchTest extends WebTestCase
{
    public function testMagazineListIsFiltered(): void
    {
        $this->loadExampleMagazines();

        $crawler = $this->client->request('GET', '/search/magazine');
        self::assertResponseIsSuccessful();
        $this->assertSelectorCount(0, '.page-search #content .magazines a');

        // unpaginated search
        $crawler = $this->client->submit(
            $crawler->filter('form[method=get]')->selectButton('')->form(['query' => 'mag'])
        );
        $actualMagazines = $crawler->filter('#content table .magazine-inline')->each(fn (Crawler $node) => $node->text());
        $this->assertSame(
            ['magazine-1', 'magazine-2', 'magazine-3', 'magazine-4'],
            $actualMagazines,
        );

        // paginated search
        $crawler = $this->client->submit(
            $crawler->filter('form[method=get]')->selectButton('')->form(['query' => 'forpagination'])
        );
        $actualMagazines = $crawler->filter('#content  table .magazine-inline')->each(fn (Crawler $node) => $node->text());
        self::assertCount(48, $actualMagazines);

        $expectedMagazines = [];
        for ($i = 1; $i <= 48; ++$i) {
            $actualMagazines[] = 'z_forpagination-'.str_pad(''.$i, 2, '0', STR_PAD_LEFT);
        }
        $this->assertSame(
            sort($expectedMagazines),
            sort($actualMagazines),
        );

        self::assertSelectorExists('.page-search #content .pagination .pagination__item--next-page');
    }

    public function loadExampleMagazines(): void
    {
        $this->getMagazineByName('magazine-1');
        $this->getMagazineByName('magazine-2');
        $this->getMagazineByName('magazine-3');
        $this->getMagazineByName('magazine-4');
        $this->getMagazineByName('testing');

        for ($i = 1; $i <= 49; ++$i) {
            $this->getMagazineByName('forpagination-'.str_pad(''.$i, 2, '0', STR_PAD_LEFT));
        }
    }
}

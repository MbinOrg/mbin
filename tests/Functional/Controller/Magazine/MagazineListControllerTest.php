<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

class MagazineListControllerTest extends WebTestCase
{
    #[DataProvider('magazines')]
    public function testMagazineListIsFiltered(array $queryParams, array $expectedMagazines): void
    {
        $this->loadExampleMagazines();

        $crawler = $this->client->request('GET', '/magazines');

        $crawler = $this->client->submit(
            $crawler->filter('form[method=get]')->selectButton('')->form($queryParams)
        );

        $actualMagazines = $crawler->filter('#content .table-responsive .magazine-inline')->each(fn (Crawler $node) => $node->innerText());

        $this->assertSame(
            sort($expectedMagazines),
            sort($actualMagazines),
        );
    }

    public static function magazines(): iterable
    {
        return [
            [['query' => 'test'], []],
            [['query' => 'acme'], ['Magazyn polityczny']],
            [['query' => '', 'adult' => 'only'], ['Adult only']],
            [['query' => 'acme', 'adult' => 'only'], []],
            [['query' => 'foobar', 'fields' => 'names_descriptions'], ['Magazyn polityczny']],
            [['adult' => 'show'], ['Magazyn polityczny', 'kbin devlog', 'Adult only', 'starwarsmemes@republic.new']],
            [['federation' => 'local'], ['Magazyn polityczny', 'kbin devlog', 'Adult only']],
            [['query' => 'starwars', 'federation' => 'local'], []],
            [['query' => 'starwars', 'federation' => 'all'], ['starwarsmemes@republic.new']],
            [['query' => 'trap', 'fields' => 'names_descriptions'], ['starwarsmemes@republic.new']],
        ];
    }
}

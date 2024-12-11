<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Domain;

use App\Tests\WebTestCase;

class DomainFrontControllerTest extends WebTestCase
{
    public function testDomainCommentFrontPage(): void
    {
        $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $crawler = $this->client->request('GET', '/');
        $crawler = $this->client->click($crawler->filter('#content article')->selectLink('More from domain')->link());

        $this->assertSelectorTextContains('#header', '/d/kbin.pub');
        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to acme');

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', 'kbin.pub');
            $this->assertSelectorTextContains('h2', ucfirst($sortOption));
        }
    }

    private function getSortOptions(): array
    {
        return ['Top', 'Hot', 'Newest', 'Active', 'Commented'];
    }
}

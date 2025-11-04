<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Domain;

use App\Tests\WebTestCase;

class DomainCommentFrontControllerTest extends WebTestCase
{
    public function testDomainCommentFrontPage(): void
    {
        $entry = $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );
        $this->createEntryComment('test comment 1', $entry);

        $crawler = $this->client->request('GET', '/d/kbin.pub');
        $crawler = $this->client->click($crawler->filter('#header')->selectLink('Comments')->link());

        $this->assertSelectorTextContains('#header', '/d/kbin.pub');
        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextContains('blockquote header', 'to acme in test entry 1');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 1');

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', 'kbin.pub');
            $this->assertSelectorTextContains('h2', ucfirst($sortOption));
        }
    }

    private function getSortOptions(): array
    {
        return ['Hot', 'Newest', 'Active', 'Oldest'];
    }
}

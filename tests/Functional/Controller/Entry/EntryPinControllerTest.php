<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryPinControllerTest extends WebTestCase
{
    public function testModCanPinEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Pin')->form([]));
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('#main .entry .fa-thumbtack');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unpin')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .entry .fa-thumbtack');
    }
}

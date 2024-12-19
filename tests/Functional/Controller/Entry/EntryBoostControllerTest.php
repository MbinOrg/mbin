<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryBoostControllerTest extends WebTestCase
{
    public function testLoggedUserCanBoostEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $this->client->submit(
            $crawler->filter('#main .entry')->selectButton('Boost')->form([])
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry', 'Boost (1)');

        $this->client->click($crawler->filter('#activity')->selectLink('Boosts (1)')->link());

        $this->assertSelectorTextContains('#main .users-columns', 'JohnDoe');
    }
}

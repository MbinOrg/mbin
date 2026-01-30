<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryChangeAdultControllerTest extends WebTestCase
{
    public function testModCanMarkAsAdultContent(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");
        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Mark NSFW')->form([
                'adult' => 'on',
            ])
        );
        $this->client->followRedirect();
        $this->assertSelectorTextContains('#main .entry .badge', '18+');

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Unmark NSFW')->form([
                'adult' => 'off',
            ])
        );
        $this->client->followRedirect();
        $this->assertSelectorTextNotContains('#main .entry', '18+');
    }
}

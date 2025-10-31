<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryLockControllerTest extends WebTestCase
{
    public function testModCanLockEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Lock')->form([]));
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('#main .entry footer span .fa-lock');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .entry footer span .fa-lock');
    }

    public function testAuthorCanLockEntry(): void
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub', user: $user);
        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}");
        $this->assertSelectorExists('#main .entry footer .dropdown .fa-lock');

        $this->client->submit($crawler->filter('#main .entry footer .dropdown')->selectButton('Lock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorExists('#main .entry footer span .fa-lock');
    }

    public function testModCanUnlockEntry(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');
        $this->entryManager->toggleLock($entry, $user);

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");
        $this->assertSelectorExists('#main .entry footer span .fa-lock');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .entry footer span .fa-lock');
    }

    public function testAuthorCanUnlockEntry(): void
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub', user: $user);
        $this->entryManager->toggleLock($entry, $user);

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}");
        $this->assertSelectorExists('#main .entry footer span .fa-lock');

        $this->client->submit($crawler->filter('#main .entry footer .dropdown')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .entry footer span .fa-lock');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryModerateControllerTest extends WebTestCase
{
    public function testModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('get', '/');
        $this->client->click($crawler->filter('#entry-'.$entry->getId())->selectLink('Moderate')->link());

        $this->assertSelectorTextContains('.moderate-panel', 'ban');
    }

    public function testXmlModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('get', '/');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('#entry-'.$entry->getId())->selectLink('Moderate')->link());

        $this->assertStringContainsString('moderate-panel', $this->client->getResponse()->getContent());
    }

    public function testUnauthorizedCanNotShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $this->client->request('get', "/m/{$entry->magazine->name}/t/{$entry->getId()}");
        $this->assertSelectorTextNotContains('#entry-'.$entry->getId(), 'Moderate');

        $this->client->request(
            'get',
            "/m/{$entry->magazine->name}/t/{$entry->getId()}/-/moderate"
        );

        $this->assertResponseStatusCodeSame(403);
    }
}

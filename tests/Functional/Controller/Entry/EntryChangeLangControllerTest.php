<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryChangeLangControllerTest extends WebTestCase
{
    public function testModCanChangeLanguage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $form = $crawler->filter('.moderate-panel')->selectButton('Change language')->form();

        $this->assertSame($form['lang']['lang']->getValue(), 'en');

        $form['lang']['lang']->select('fr');

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .badge-lang', 'French');
    }
}

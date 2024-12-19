<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryEditControllerTest extends WebTestCase
{
    public function testAuthorCanEditOwnEntryLink(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');
        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $crawler = $this->client->click($crawler->filter('#main .entry')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .entry');

        $this->assertInputValueSame('entry_edit[url]', 'https://kbin.pub');
        $this->assertEquals('disabled', $crawler->filter('#entry_edit_magazine')->attr('disabled'));

        $this->client->submit(
            $crawler->filter('form[name=entry_edit]')->selectButton('Edit thread')->form(
                [
                    'entry_edit[title]' => 'test entry 2 title',
                    'entry_edit[body]' => 'test entry 2 body',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry header', 'test entry 2 title');
        $this->assertSelectorTextContains('#main .entry .entry__body', 'test entry 2 body');
    }

    public function testAuthorCanEditOwnEntryArticle(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', null, 'entry content test entry 1');
        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $crawler = $this->client->click($crawler->filter('#main .entry')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .entry');

        $this->assertEquals('disabled', $crawler->filter('#entry_edit_magazine')->attr('disabled'));

        $this->client->submit(
            $crawler->filter('form[name=entry_edit]')->selectButton('Edit thread')->form(
                [
                    'entry_edit[title]' => 'test entry 2 title',
                    'entry_edit[body]' => 'test entry 2 body',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry header', 'test entry 2 title');
        $this->assertSelectorTextContains('#main .entry .entry__body', 'test entry 2 body');
    }

    public function testAuthorCanEditOwnEntryImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test entry 1', image: $imageDto);
        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->click($crawler->filter('#main .entry')->selectLink('Edit')->link());
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('#main .entry');
        $this->assertSelectorExists('#main .entry img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);

        $this->assertEquals('disabled', $crawler->filter('#entry_edit_magazine')->attr('disabled'));

        $this->client->submit(
            $crawler->filter('form[name=entry_edit]')->selectButton('Edit thread')->form(
                [
                    'entry_edit[title]' => 'test entry 2 title',
                ]
            )
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry header', 'test entry 2 title');
        $this->assertSelectorExists('#main .entry img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);
    }
}

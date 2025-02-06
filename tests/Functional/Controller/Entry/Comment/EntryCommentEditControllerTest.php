<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class EntryCommentEditControllerTest extends WebTestCase
{
    public function testAuthorCanEditOwnEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');
        $this->createEntryComment('test comment 1', $entry);

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $crawler = $this->client->click($crawler->filter('#main .entry-comment')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .entry-comment');

        $this->assertSelectorTextContains('#main .entry-comment', 'test comment 1');

        $this->client->submit(
            $crawler->filter('form[name=entry_comment]')->selectButton('Update comment')->form(
                [
                    'entry_comment[body]' => 'test comment 2 body',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry-comment', 'test comment 2 body');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testAuthorCanEditOwnEntryCommentWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $imageDto = $this->getKibbyImageDto();
        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');
        $this->createEntryComment('test comment 1', $entry, imageDto: $imageDto);

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $crawler = $this->client->click($crawler->filter('#main .entry-comment')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .entry-comment');

        $this->assertSelectorTextContains('#main .entry-comment', 'test comment 1');
        $this->assertSelectorExists('#main .entry-comment img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);

        $this->client->submit(
            $crawler->filter('form[name=entry_comment]')->selectButton('Update comment')->form(
                [
                    'entry_comment[body]' => 'test comment 2 body',
                ]
            )
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .entry-comment', 'test comment 2 body');
        $this->assertSelectorExists('#main .entry-comment img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);
    }
}

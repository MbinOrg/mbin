<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\Tests\WebTestCase;

class EntryCommentBoostControllerTest extends WebTestCase
{
    public function testLoggedUserCanAddToBoostsEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            null,
            $this->getUserByUsername('JaneDoe')
        );
        $this->createEntryComment('test comment 1', $entry, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");
        $this->client->submit(
            $crawler->filter('#main .entry-comment')->selectButton('Boost')->form()
        );
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $this->assertSelectorTextContains('#main .entry-comment', 'Boost (1)');

        $crawler = $this->client->click($crawler->filter('#main .entry-comment')->selectLink('Activity')->link());

        $this->client->click($crawler->filter('#main #activity')->selectLink('Boosts (1)')->link());

        $this->assertSelectorTextContains('#main .users-columns', 'JohnDoe');
    }
}

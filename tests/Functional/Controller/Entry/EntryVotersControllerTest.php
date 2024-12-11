<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Entity\Contracts\VotableInterface;
use App\Service\VoteManager;
use App\Tests\WebTestCase;

class EntryVotersControllerTest extends WebTestCase
{
    public function testUserCanSeeUpVoters(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $manager = $this->client->getContainer()->get(VoteManager::class);
        $manager->vote(VotableInterface::VOTE_UP, $entry, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $this->client->click($crawler->filter('.options-activity')->selectLink('Boosts (1)')->link());

        $this->assertSelectorTextContains('#main .users-columns', 'JaneDoe');
    }

    public function testUserCannotSeeDownVoters(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $manager = $this->client->getContainer()->get(VoteManager::class);
        $manager->vote(VotableInterface::VOTE_DOWN, $entry, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $crawler = $crawler->filter('.options-activity')->selectLink('Reduces (1)');
        self::assertEquals(0, $crawler->count());

        $this->assertSelectorTextContains('.options-activity', 'Reduces (1)');
    }
}

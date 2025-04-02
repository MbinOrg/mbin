<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class UserBlockControllerTest extends WebTestCase
{
    public function testUserCanBlockAndUnblock(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId().'/test-entry-1');

        // Block
        $this->client->submit($crawler->filter('#sidebar form[name=user_block] button')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=user_block] .active');

        // Unblock
        $this->client->submit($crawler->filter('#sidebar form[name=user_block] button')->form());
        $this->client->followRedirect();

        $this->assertSelectorNotExists('#sidebar form[name=user_block] .active');
    }

    public function testXmlUserCanBlock(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId().'/test-entry-1');

        // Block
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=user_block] button')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('active', $this->client->getResponse()->getContent());
    }

    #[Group(name: 'NonThreadSafe')]
    public function testXmlUserCanUnblock(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId().'/test-entry-1');

        // Block
        $this->client->submit($crawler->filter('#sidebar form[name=user_block] button')->form());
        $crawler = $this->client->followRedirect();

        // Unblock
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=user_block] button')->form());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('active', $this->client->getResponse()->getContent());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine;

use App\Tests\WebTestCase;

class MagazineBlockControllerTest extends WebTestCase
{
    public function testUserCanBlockAndUnblockMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        // Block magazine
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('#sidebar form[name=magazine_block] .active');

        // Unblock magazine
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#sidebar form[name=magazine_block] .active');
    }

    public function testXmlUserCanBlockMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('active', $this->client->getResponse()->getContent());
    }

    public function testXmlUserCanUnblockMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        // Block magazine
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());
        $crawler = $this->client->followRedirect();

        // Unblock magazine
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=magazine_block] button')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('active', $this->client->getResponse()->getContent());
    }
}

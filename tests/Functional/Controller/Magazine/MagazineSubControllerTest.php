<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine;

use App\Tests\WebTestCase;

class MagazineSubControllerTest extends WebTestCase
{
    public function testUserCanSubAndUnsubMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        // Sub magazine
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Sub')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=magazine_subscribe] .active');
        $this->assertSelectorTextContains('#sidebar .magazine', 'Unsub');
        $this->assertSelectorTextContains('#sidebar .magazine', '2');

        // Unsub magazine
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Unsub')->form());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('#sidebar .magazine', '1');
    }

    public function testXmlUserCanSubMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Sub')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Unsub', $this->client->getResponse()->getContent());
    }

    public function testXmlUserCanUnsubMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        // Sub magazine
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Sub')->form());
        $crawler = $this->client->followRedirect();

        // Unsub magazine
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Unsub')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Sub', $this->client->getResponse()->getContent());
    }
}

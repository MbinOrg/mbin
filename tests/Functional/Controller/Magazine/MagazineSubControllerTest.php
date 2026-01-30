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
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Subscribe')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=magazine_subscribe] .active');
        $this->assertSelectorTextContains('#sidebar .magazine', 'Unsubscribe');
        $this->assertSelectorTextContains('#sidebar .magazine', '2');

        // Unsub magazine
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Unsubscribe')->form());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('#sidebar .magazine', '1');
    }

    public function testXmlUserCanSubMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Subscribe')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Unsubscribe', $this->client->getResponse()->getContent());
    }

    public function testXmlUserCanUnsubMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme');

        // Sub magazine
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Subscribe')->form());
        $crawler = $this->client->followRedirect();

        // Unsub magazine
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .magazine')->selectButton('Unsubscribe')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Subscribe', $this->client->getResponse()->getContent());
    }
}

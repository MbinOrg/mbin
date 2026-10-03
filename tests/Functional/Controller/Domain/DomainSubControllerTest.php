<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Domain;

use App\Tests\WebTestCase;

class DomainSubControllerTest extends WebTestCase
{
    public function testUserCanSubAndUnsubDomain(): void
    {
        $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        // Subscribe
        $this->client->submit($crawler->filter('#sidebar .domain')->selectButton('Sub')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=domain_subscribe] .active');
        $this->assertSelectorTextContains('#sidebar .domain', 'Unsub');
        $this->assertSelectorTextContains('#sidebar .domain', '1');

        // Unsubscribe
        $this->client->submit($crawler->filter('#sidebar .domain')->selectButton('Unsub')->form());
        $this->client->followRedirect();

        $this->assertSelectorNotExists('#sidebar form[name=domain_subscribe] .active');
        $this->assertSelectorTextContains('#sidebar .domain', 'Sub');
        $this->assertSelectorTextContains('#sidebar .domain', '0');
    }

    public function testXmlUserCanSubDomain(): void
    {
        $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        // Subscribe
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .domain')->selectButton('Sub')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Unsub', $this->client->getResponse()->getContent());
    }

    public function testXmlUserCanUnsubDomain(): void
    {
        $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        // Subscribe
        $this->client->submit($crawler->filter('#sidebar .domain')->selectButton('Sub')->form());
        $crawler = $this->client->followRedirect();

        // Unsubscribe
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .domain')->selectButton('Unsub')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Sub', $this->client->getResponse()->getContent());
    }
}

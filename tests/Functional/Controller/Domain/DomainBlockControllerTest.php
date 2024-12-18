<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Domain;

use App\Tests\WebTestCase;

class DomainBlockControllerTest extends WebTestCase
{
    public function testUserCanBlockAndUnblockDomain(): void
    {
        $entry = $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        // Block
        $this->client->submit($crawler->filter('#sidebar form[name=domain_block] button')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=domain_block] .active');

        // Unblock
        $this->client->submit($crawler->filter('#sidebar form[name=domain_block] button')->form());
        $this->client->followRedirect();

        $this->assertSelectorNotExists('#sidebar form[name=domain_block] .active');
    }

    public function testXmlUserCanBlockDomain(): void
    {
        $entry = $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=domain_block] button')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('active', $this->client->getResponse()->getContent());
    }

    public function testXmlUserCanUnblockDomain(): void
    {
        $entry = $this->createEntry(
            'test entry 1',
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('JohnDoe'),
            'http://kbin.pub/instances'
        );

        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', '/d/kbin.pub');

        // Block
        $this->client->submit($crawler->filter('#sidebar form[name=domain_block] button')->form());
        $crawler = $this->client->followRedirect();

        // Unblock
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar form[name=domain_block] button')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('active', $this->client->getResponse()->getContent());
    }
}

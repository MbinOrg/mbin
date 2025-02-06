<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class UserFollowControllerTest extends WebTestCase
{
    #[Group(name: 'NonThreadSafe')]
    public function testUserCanFollowAndUnfollow(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId());

        // Follow
        $this->client->submit($crawler->filter('#sidebar .entry-info')->selectButton('Follow')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorExists('#sidebar form[name=user_follow] .active');
        $this->assertSelectorTextContains('#sidebar .entry-info', 'Unfollow');
        $this->assertSelectorTextContains('#sidebar .entry-info', '1');

        // Unfollow
        $this->client->submit($crawler->filter('#sidebar .entry-info')->selectButton('Unfollow')->form());
        $this->client->followRedirect();

        $this->assertSelectorNotExists('#sidebar form[name=user_follow] .active');
        $this->assertSelectorTextContains('#sidebar .entry-info', 'Follow');
        $this->assertSelectorTextContains('#sidebar .entry-info', '0');
    }

    public function testXmlUserCanFollow(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId());

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .entry-info')->selectButton('Follow')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Unfollow', $this->client->getResponse()->getContent());
    }

    #[Group(name: 'NonThreadSafe')]
    public function testXmlUserCanUnfollow(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId());

        // Follow
        $this->client->submit($crawler->filter('#sidebar .entry-info')->selectButton('Follow')->form());
        $crawler = $this->client->followRedirect();

        // Unfollow
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->submit($crawler->filter('#sidebar .entry-info')->selectButton('Unfollow')->form());

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Follow', $this->client->getResponse()->getContent());
    }
}

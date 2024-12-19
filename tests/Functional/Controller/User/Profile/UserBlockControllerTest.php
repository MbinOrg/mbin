<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User\Profile;

use App\Tests\WebTestCase;

class UserBlockControllerTest extends WebTestCase
{
    public function testUserCanSeeBlockedMagazines()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('acme');

        $this->magazineManager->block($magazine, $user);

        $crawler = $this->client->request('GET', '/settings/blocked/magazines');
        $this->client->click($crawler->filter('#main .pills')->selectLink('Magazines')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'Magazines');
        $this->assertSelectorTextContains('#main .magazines', 'acme');
    }

    public function testUserCanSeeBlockedUsers()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));

        $this->userManager->block($user, $this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/settings/blocked/people');
        $this->client->click($crawler->filter('#main .pills')->selectLink('People')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'People');
        $this->assertSelectorTextContains('#main .users', 'JohnDoe');
    }

    public function testUserCanSeeBlockedDomains()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test1', 'https://kbin.pub');

        $this->domainManager->block($entry->domain, $user);

        $crawler = $this->client->request('GET', '/settings/blocked/domains');
        $this->client->click($crawler->filter('#main .pills')->selectLink('Domains')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'Domains');
        $this->assertSelectorTextContains('#main', 'kbin.pub');
    }
}

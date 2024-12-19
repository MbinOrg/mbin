<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User\Profile;

use App\Tests\WebTestCase;

class UserSubControllerTest extends WebTestCase
{
    public function testUserCanSeeSubscribedMagazines()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));
        $magazine = $this->getMagazineByName('acme');

        $this->magazineManager->subscribe($magazine, $user);

        $crawler = $this->client->request('GET', '/settings/subscriptions/magazines');
        $this->client->click($crawler->filter('#main .pills')->selectLink('Magazines')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'Magazines');
        $this->assertSelectorTextContains('#main .magazines', 'acme');
    }

    public function testUserCanSeeSubscribedUsers()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));

        $this->userManager->follow($user, $this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/settings/subscriptions/people');
        $this->client->click($crawler->filter('#main .pills')->selectLink('People')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'People');
        $this->assertSelectorTextContains('#main .users', 'JohnDoe');
    }

    public function testUserCanSeeSubscribedDomains()
    {
        $this->client->loginUser($user = $this->getUserByUsername('JaneDoe'));

        $entry = $this->getEntryByTitle('test1', 'https://kbin.pub');

        $this->domainManager->subscribe($entry->domain, $user);

        $crawler = $this->client->request('GET', '/settings/subscriptions/domains');
        $this->client->click($crawler->filter('#main .pills')->selectLink('Domains')->link());

        $this->assertSelectorTextContains('#main .pills .active', 'Domains');
        $this->assertSelectorTextContains('#main', 'kbin.pub');
    }
}

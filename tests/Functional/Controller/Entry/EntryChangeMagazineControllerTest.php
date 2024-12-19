<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;

class EntryChangeMagazineControllerTest extends WebTestCase
{
    public function testAdminCanChangeMagazine(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->setAdmin($user);
        $this->client->loginUser($user);

        $this->getMagazineByName('kbin');

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
        );

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $this->client->submit(
            $crawler->filter('form[name=change_magazine]')->selectButton('Change magazine')->form(
                [
                    'change_magazine[new_magazine]' => 'kbin',
                ]
            )
        );

        $this->client->followRedirect();
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.head-title', 'kbin');
    }

    public function testUnauthorizedUserCantChangeMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $this->getMagazineByName('kbin');

        $entry = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
        );

        $this->client->request('GET', "/m/acme/t/{$entry->getId()}/-/moderate");

        $this->assertSelectorTextNotContains('.moderate-panel', 'Change magazine');
    }
}

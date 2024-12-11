<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\Tests\WebTestCase;

class MagazineBadgeControllerTest extends WebTestCase
{
    public function testModCanAddAndRemoveBadge(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getMagazineByName('acme');

        // Add badge
        $crawler = $this->client->request('GET', '/m/acme/panel/badges');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Badges');
        $this->client->submit(
            $crawler->filter('#main form[name=badge]')->selectButton('Add badge')->form([
                'badge[name]' => 'test',
            ])
        );

        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('#main .badges', 'test');

        // Remove badge
        $this->client->submit(
            $crawler->filter('#main .badges')->selectButton('Delete')->form()
        );

        $this->client->followRedirect();
        $this->assertSelectorTextContains('#main .section--muted', 'Empty');
    }

    public function testUnauthorizedUserCannotAddBadge(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/badges');

        $this->assertResponseStatusCodeSame(403);
    }
}

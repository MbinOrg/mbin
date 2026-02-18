<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\Tests\WebTestCase;

class MagazineBanControllerTest extends WebTestCase
{
    public function testModCanAddAndRemoveBan(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getUserByUsername('JaneDoe');
        $this->getMagazineByName('acme');

        // Add ban
        $crawler = $this->client->request('GET', '/m/acme/panel/bans');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Bans');
        $crawler = $this->client->submit(
            $crawler->filter('#main form[name=ban]')->selectButton('Add ban')->form([
                'username' => 'JaneDoe',
            ])
        );

        $this->client->submit(
            $crawler->filter('#main form[name=magazine_ban]')->selectButton('Ban')->form([
                'magazine_ban[reason]' => 'Reason test',
                'magazine_ban[expiredAt]' => (new \DateTimeImmutable('+2 weeks'))->format('Y-m-d H:i:s'),
            ])
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .bans-table', 'JaneDoe');

        // Remove ban
        $this->client->submit(
            $crawler->filter('#main .bans-table')->selectButton('Delete')->form()
        );

        $this->client->followRedirect();
        $this->assertSelectorTextContains('#main', 'Empty');
    }

    public function testUnauthorizedUserCannotAddBan(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/bans');

        $this->assertResponseStatusCodeSame(403);
    }
}

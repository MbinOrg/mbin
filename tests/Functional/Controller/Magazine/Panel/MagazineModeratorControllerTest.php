<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\Tests\WebTestCase;

class MagazineModeratorControllerTest extends WebTestCase
{
    public function testOwnerCanAddAndRemoveModerator(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getUserByUsername('JaneDoe');
        $this->getMagazineByName('acme');

        // Add moderator
        $crawler = $this->client->request('GET', '/m/acme/panel/moderators');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Moderators');
        $crawler = $this->client->submit(
            $crawler->filter('#main form[name=moderator]')->selectButton('Add moderator')->form([
                'moderator[user]' => 'JaneDoe',
            ])
        );
        $this->assertSelectorTextContains('#main .users-columns', 'JaneDoe');
        $this->assertEquals(2, $crawler->filter('#main .users-columns ul li')->count());

        // Remove moderator
        $this->client->submit(
            $crawler->filter('#main .users-columns')->selectButton('Delete')->form()
        );

        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextNotContains('#main .users-columns', 'JaneDoe');
        $this->assertEquals(1, $crawler->filter('#main .users-columns ul li')->count());
    }

    public function testUnauthorizedUserCannotAddModerator(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/moderators');

        $this->assertResponseStatusCodeSame(403);
    }
}

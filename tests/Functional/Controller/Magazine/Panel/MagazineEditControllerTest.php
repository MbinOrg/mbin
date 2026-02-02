<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\DTO\ModeratorDto;
use App\Tests\WebTestCase;

class MagazineEditControllerTest extends WebTestCase
{
    public function testModCannotSeePanelLink(): void
    {
        $mod = $this->getUserByUsername('JohnDoe');
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $this->client->loginUser($mod);
        $magazine = $this->getMagazineByName('acme', $admin);

        $manager = $this->magazineManager;
        $dto = new ModeratorDto($magazine, $mod, $admin);
        $manager->addModerator($dto);

        $this->client->request('GET', '/m/acme');
        $this->assertSelectorTextNotContains('#sidebar .magazine', 'Magazine panel');
    }

    public function testOwnerCanEditMagazine(): void
    {
        $owner = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($owner);
        $magazine = $this->getMagazineByName('acme', $owner);
        $magazine->rules = 'init rules';
        $this->entityManager->persist($magazine);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/m/acme/panel/general');
        self::assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#main .options__main a.active', 'General');
        $this->client->submit(
            $crawler->filter('#main form[name=magazine]')->selectButton('Done')->form([
                'magazine[description]' => 'test description edit',
                'magazine[rules]' => 'test rules edit',
                'magazine[isAdult]' => true,
            ])
        );

        $this->client->followRedirect();
        $this->assertSelectorTextContains('#sidebar .magazine', 'test description edit');
        $this->assertSelectorTextContains('#sidebar .magazine', 'test rules edit');
    }

    public function testCannotEditRulesWhenEmpty(): void
    {
        $owner = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($owner);
        $this->getMagazineByName('acme', $owner);

        $crawler = $this->client->request('GET', '/m/acme/panel/general');
        self::assertResponseIsSuccessful();
        $exception = null;
        try {
            $crawler->filter('#main form[name=magazine]')->selectButton('Done')->form([
                'magazine[rules]' => 'test rules edit',
            ]);
        } catch (\Exception $e) {
            $exception = $e;
        }
        self::assertNotNull($exception);
        self::assertStringContainsString('Unreachable field "rules"', $exception->getMessage());
    }

    public function testUnauthorizedUserCannotEditMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/general');

        $this->assertResponseStatusCodeSame(403);
    }
}

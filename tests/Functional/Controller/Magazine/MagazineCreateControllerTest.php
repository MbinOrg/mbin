<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine;

use App\Tests\WebTestCase;

class MagazineCreateControllerTest extends WebTestCase
{
    public function testUserCanCreateMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/newMagazine');

        $this->client->submit(
            $crawler->filter('form[name=magazine]')->selectButton('Create new magazine')->form(
                [
                    'magazine[name]' => 'TestMagazine',
                    'magazine[title]' => 'Test magazine title',
                ]
            )
        );

        $this->assertResponseRedirects('/m/TestMagazine');

        $this->client->followRedirect();

        $this->assertSelectorTextContains('.head-title', '/m/TestMagazine');
        $this->assertSelectorTextContains('#content', 'Empty');
    }

    public function testUserCantCreateInvalidMagazine(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/newMagazine');

        $this->client->submit(
            $crawler->filter('form[name=magazine]')->selectButton('Create new magazine')->form(
                [
                    'magazine[name]' => 't',
                    'magazine[title]' => 'Test magazine title',
                ]
            )
        );

        $this->assertSelectorTextContains('#content', 'This value is too short. It should have 2 characters or more.');
    }

    public function testUserCantCreateTwoSameMagazines(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/newMagazine');

        $this->client->submit(
            $crawler->filter('form[name=magazine]')->selectButton('Create new magazine')->form(
                [
                    'magazine[name]' => 'acme',
                    'magazine[title]' => 'Test magazine title',
                ]
            )
        );

        $this->assertSelectorTextContains('#content', 'This value is already used.');
    }
}

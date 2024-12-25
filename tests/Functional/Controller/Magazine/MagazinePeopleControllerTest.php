<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine;

use App\Tests\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class MagazinePeopleControllerTest extends WebTestCase
{
    public function testMagazinePeoplePage(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->createPost('test post content');

        $user->about = 'Loerm ipsum';
        $this->getService(EntityManagerInterface::class)->flush();

        $crawler = $this->client->request('GET', '/m/acme/people');

        $this->assertEquals(1, $crawler->filter('#main .user-box')->count());
        $this->assertSelectorTextContains('#main .users .user-box', 'Loerm ipsum');
    }
}

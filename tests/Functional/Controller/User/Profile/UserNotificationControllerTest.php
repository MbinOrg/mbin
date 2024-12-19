<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User\Profile;

use App\Tests\WebTestCase;

class UserNotificationControllerTest extends WebTestCase
{
    public function testUserReceiveNotificationTest(): void
    {
        $this->client->loginUser($owner = $this->getUserByUsername('owner'));

        $actor = $this->getUserByUsername('actor');

        $this->magazineManager->subscribe($this->getMagazineByName('acme'), $owner);
        $this->magazineManager->subscribe($this->getMagazineByName('acme'), $actor);

        $this->loadNotificationsFixture();

        $crawler = $this->client->request('GET', '/settings/notifications');
        $this->assertCount(2, $crawler->filter('#main .notification'));

        $this->client->restart();
        $this->client->loginUser($actor);

        $crawler = $this->client->request('GET', '/settings/notifications');
        $this->assertCount(3, $crawler->filter('#main .notification'));

        $this->client->restart();
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/settings/notifications');
        $this->assertCount(2, $crawler->filter('#main .notification'));
    }

    public function testCanReadAllNotifications(): void
    {
        $this->client->loginUser($this->getUserByUsername('owner'));

        $this->magazineManager->subscribe(
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('owner')
        );
        $this->magazineManager->subscribe(
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('actor')
        );

        $this->loadNotificationsFixture();

        $this->client->loginUser($this->getUserByUsername('owner'));

        $crawler = $this->client->request('GET', '/settings/notifications');

        $this->assertCount(2, $crawler->filter('#main .notification'));
        $this->assertCount(0, $crawler->filter('#main .notification.opacity-50'));

        $this->client->submit($crawler->selectButton('Read all')->form());

        $crawler = $this->client->followRedirect();

        $this->assertCount(2, $crawler->filter('#main .notification.opacity-50'));
    }

    public function testUserCanDeleteAllNotifications(): void
    {
        $this->client->loginUser($this->getUserByUsername('owner'));

        $this->magazineManager->subscribe(
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('owner')
        );
        $this->magazineManager->subscribe(
            $this->getMagazineByName('acme'),
            $this->getUserByUsername('actor')
        );

        $this->loadNotificationsFixture();

        $this->client->loginUser($this->getUserByUsername('owner'));

        $crawler = $this->client->request('GET', '/settings/notifications');

        $this->assertCount(2, $crawler->filter('#main .notification'));

        $this->client->submit($crawler->selectButton('Purge')->form());

        $crawler = $this->client->followRedirect();

        $this->assertCount(0, $crawler->filter('#main .notification'));
    }
}

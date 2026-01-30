<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\WebTestCase;

class AdminFederationControllerTest extends WebTestCase
{
    public function testAdminCanClearBannedInstances(): void
    {
        $instance = $this->instanceRepository->getOrCreateInstance('www.example.com');
        $this->instanceManager->banInstance($instance);

        $this->client->loginUser($this->getUserByUsername('admin', isAdmin: true));

        $crawler = $this->client->request('GET', '/admin/federation');

        $this->client->submit($crawler->filter('#content tr td button[type=submit]')->form());

        $this->assertSame(
            [],
            $this->settingsManager->getBannedInstances(),
        );
    }
}

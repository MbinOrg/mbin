<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\WebTestCase;

class AdminFederationControllerTest extends WebTestCase
{
    public function testAdminCanClearBannedInstances(): void
    {
        $this->settingsManager->set('KBIN_BANNED_INSTANCES', ['www.example.com']);

        $this->client->loginUser($this->getUserByUsername('admin', isAdmin: true));

        $crawler = $this->client->request('GET', '/admin/federation');

        $this->client->submit($crawler->filter('#content form[name=instances] button[type=submit]')->form(
            ['instances[instances]' => ''],
        ));

        $this->assertSame(
            [],
            $this->settingsRepository->findOneBy(['name' => 'KBIN_BANNED_INSTANCES'])->json,
        );
    }
}

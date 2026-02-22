<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\WebTestCase;

class AdminUserControllerTest extends WebTestCase
{
    public function testInactiveUser(): void
    {
        $this->getUserByUsername('inactiveUser', active: false);
        $admin = $this->getUserByUsername('admin', isAdmin: true);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/users/inactive');
        self::assertResponseIsSuccessful();

        self::assertAnySelectorTextContains('a.user-inline', 'inactiveUser');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Security;

use App\Tests\WebTestCase;

class LoginControllerTest extends WebTestCase
{
    public function testUserCanLogin(): void
    {
        $this->client = $this->register(true);

        $crawler = $this->client->request('get', '/');
        $crawler = $this->client->click($crawler->filter('header')->selectLink('Log in')->link());

        $this->client->submit(
            $crawler->selectButton('Log in')->form(
                [
                    'email' => 'JohnDoe',
                    'password' => 'secret',
                ]
            )
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#header', 'JohnDoe');
    }

    public function testUserCannotLoginWithoutActivation(): void
    {
        $this->client = $this->register();

        $crawler = $this->client->request('get', '/');
        $crawler = $this->client->click($crawler->filter('header')->selectLink('Log in')->link());

        $this->client->submit(
            $crawler->selectButton('Log in')->form(
                [
                    'email' => 'JohnDoe',
                    'password' => 'secret',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main', 'Please check your email for account activation instructions or request a new account activation email');
    }

    public function testUserCantLoginWithWrongPassword(): void
    {
        $this->getUserByUsername('JohnDoe');

        $crawler = $this->client->request('GET', '/');
        $crawler = $this->client->click($crawler->filter('header')->selectLink('Log in')->link());

        $this->client->submit(
            $crawler->selectButton('Log in')->form(
                [
                    'email' => 'JohnDoe',
                    'password' => 'wrongpassword',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('.alert__danger', 'Invalid credentials.'); // @todo
    }
}

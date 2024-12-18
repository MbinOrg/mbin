<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Security;

use App\Tests\WebTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

class RegisterControllerTest extends WebTestCase
{
    public function testUserCanVerifyAccount(): void
    {
        $this->registerUserAccount($this->client);

        $this->assertEmailCount(1);

        /** @var TemplatedEmail $email */
        $email = $this->getMailerMessage();

        $this->assertEmailHeaderSame($email, 'To', 'johndoe@kbin.pub');

        $verificationLink = (new Crawler($email->getHtmlBody()))
            ->filter('a.btn.btn__primary')
            ->attr('href')
        ;

        $this->client->request('GET', $verificationLink);
        $crawler = $this->client->followRedirect();

        $this->client->submit(
            $crawler->selectButton('Log in')->form(
                [
                    'email' => 'JohnDoe',
                    'password' => 'secret',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextNotContains('#header', 'Log in');
    }

    private function registerUserAccount(KernelBrowser $client): void
    {
        $crawler = $client->request('GET', '/register');

        $client->submit(
            $crawler->filter('form[name=user_register]')->selectButton('Register')->form(
                [
                    'user_register[username]' => 'JohnDoe',
                    'user_register[email]' => 'johndoe@kbin.pub',
                    'user_register[plainPassword][first]' => 'secret',
                    'user_register[plainPassword][second]' => 'secret',
                    'user_register[agreeTerms]' => true,
                ]
            )
        );
    }

    public function testUserCannotLoginWithoutConfirmation()
    {
        $this->registerUserAccount($this->client);

        $crawler = $this->client->followRedirect();

        $crawler = $this->client->click($crawler->filter('#header')->selectLink('Log in')->link());

        $this->client->submit(
            $crawler->selectButton('Log in')->form(
                [
                    'email' => 'JohnDoe',
                    'password' => 'wrong_password',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('.alert__danger', 'Your account has not been activated.');
    }
}

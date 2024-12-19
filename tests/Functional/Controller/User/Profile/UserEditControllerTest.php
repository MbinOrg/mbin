<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User\Profile;

use App\Tests\WebTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DomCrawler\Crawler;

class UserEditControllerTest extends WebTestCase
{
    public string $kibbyPath;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->kibbyPath = \dirname(__FILE__, 5).'/assets/kibby_emoji.png';
    }

    public function testUserCanSeeSettingsLink(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/');
        $this->client->click($crawler->filter('#header menu')->selectLink('Settings')->link());

        $this->assertSelectorTextContains('#main .options__main a.active', 'General');
    }

    public function testUserCanEditProfile(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', '/settings/profile');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Profile');

        $this->client->submit(
            $crawler->filter('#main form[name=user_basic]')->selectButton('Save')->form([
                'user_basic[about]' => 'test about',
            ])
        );

        $this->client->followRedirect();
        $this->assertSelectorTextContains('#main .user-box', 'test about');

        $this->client->request('GET', '/people');

        $this->assertSelectorTextContains('#main .user-box', 'JohnDoe');
    }

    public function testUserCanUploadAvatar(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        $repository = $this->userRepository;

        $crawler = $this->client->request('GET', '/settings/profile');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Profile');
        $this->assertStringContainsString('/dev/random', $user->avatar->filePath);

        $form = $crawler->filter('#main form[name=user_basic]')->selectButton('Save')->form();
        $form['user_basic[avatar]']->upload($this->kibbyPath);
        $this->client->submit($form);

        $user = $repository->find($user->getId());
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $user->avatar->filePath);
    }

    public function testUserCanUploadCover(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);
        $repository = $this->userRepository;

        $crawler = $this->client->request('GET', '/settings/profile');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Profile');
        $this->assertNull($user->cover);

        $form = $crawler->filter('#main form[name=user_basic]')->selectButton('Save')->form();
        $form['user_basic[cover]']->upload($this->kibbyPath);
        $this->client->submit($form);

        $user = $repository->find($user->getId());
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $user->cover->filePath);
    }

    public function testUserCanChangePassword(): void
    {
        $this->client = $this->register(true);

        $this->client->loginUser($this->userRepository->findOneBy(['username' => 'JohnDoe']));

        $crawler = $this->client->request('GET', '/settings/password');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Password');

        $this->client->submit(
            $crawler->filter('#main form[name=user_password]')->selectButton('Save')->form([
                'user_password[currentPassword]' => 'secret',
                'user_password[plainPassword][first]' => 'test123',
                'user_password[plainPassword][second]' => 'test123',
            ])
        );

        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/login');

        $this->client->submit(
            $crawler->filter('#main form')->selectButton('Log in')->form([
                'email' => 'JohnDoe',
                'password' => 'test123',
            ])
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#header', 'JohnDoe');
    }

    public function testUserCanChangeEmail(): void
    {
        $this->client = $this->register(true);

        $this->client->loginUser($this->userRepository->findOneBy(['username' => 'JohnDoe']));

        $crawler = $this->client->request('GET', '/settings/email');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Email');

        $this->client->submit(
            $crawler->filter('#main form[name=user_email]')->selectButton('Save')->form([
                'user_email[newEmail][first]' => 'acme@kbin.pub',
                'user_email[newEmail][second]' => 'acme@kbin.pub',
                'user_email[currentPassword]' => 'secret',
            ])
        );

        $this->assertEmailCount(1);

        /** @var TemplatedEmail $email */
        $email = $this->getMailerMessage();

        $this->assertEmailHeaderSame($email, 'To', 'acme@kbin.pub');

        $verificationLink = (new Crawler($email->getHtmlBody()))
            ->filter('a.btn.btn__primary')
            ->attr('href')
        ;

        $this->client->request('GET', $verificationLink);

        $crawler = $this->client->followRedirect();

        $this->client->submit(
            $crawler->filter('#main form')->selectButton('Log in')->form([
                'email' => 'JohnDoe',
                'password' => 'secret',
            ])
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#header', 'JohnDoe');
    }
}

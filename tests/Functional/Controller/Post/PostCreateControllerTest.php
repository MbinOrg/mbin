<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostCreateControllerTest extends WebTestCase
{
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->kibbyPath = \dirname(__FILE__, 4).'/assets/kibby_emoji.png';
    }

    public function testUserCanCreatePost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/microblog');

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Add post')->form(
                [
                    'post[body]' => 'test post 1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/microblog/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#content .post', 'test post 1');
    }

    public function testUserCanCreatePostWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/microblog');

        $form = $crawler->filter('form[name=post]')->selectButton('Add post')->form();
        $form->get('post[body]')->setValue('test post 1');
        $form->get('post[image]')->upload($this->kibbyPath);
        // Needed since we require this global to be set when validating entries but the client doesn't actually set it
        $_FILES = $form->getPhpFiles();
        $this->client->submit($form);

        $this->assertResponseRedirects('/m/acme/microblog/newest');
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#content .post', 'test post 1');
        $this->assertSelectorExists('#content .post footer figure img');
        $imgSrc = $crawler->filter('#content .post footer figure img')->getNode(0)->attributes->getNamedItem('src')->textContent;
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $imgSrc);
        $_FILES = [];
    }

    public function testUserCannotCreateInvalidPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/microblog');

        $crawler = $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Add post')->form(
                [
                    'post[body]' => '',
                ]
            )
        );

        self::assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#content', 'This value should not be blank.');
    }

    public function testCreatedPostIsMarkedAsForAdults(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe', hideAdult: false));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/microblog');

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Add post')->form(
                [
                    'post[body]' => 'test nsfw 1',
                    'post[isAdult]' => '1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/microblog/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('blockquote header .danger', '18+');
    }

    public function testPostCreatedInAdultMagazineIsAutomaticallyMarkedAsForAdults(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe', hideAdult: false));

        $this->getMagazineByName('adult', isAdult: true);

        $crawler = $this->client->request('GET', '/m/adult/microblog');

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Add post')->form(
                [
                    'post[body]' => 'test nsfw 1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/adult/microblog/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('blockquote header .danger', '18+');
    }
}

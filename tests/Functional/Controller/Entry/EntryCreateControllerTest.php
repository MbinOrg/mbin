<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class EntryCreateControllerTest extends WebTestCase
{
    public string $kibbyPath;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->kibbyPath = \dirname(__FILE__, 4).'/assets/kibby_emoji.png';
    }

    public function testUserCanCreateEntryLink()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', '/m/acme/new');

        $this->assertSelectorExists('form[name=entry_link]');
    }

    public function testUserCanCreateEntryLinkFromMagazinePage(): void
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/new');

        $this->client->submit(
            $crawler->filter('form[name=entry_link]')->selectButton('Add new link')->form(
                [
                    'entry_link[url]' => 'https://kbin.pub',
                    'entry_link[title]' => 'Test entry 1',
                    'entry_link[body]' => 'Test body',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/threads/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
    }

    public function testUserCanCreateEntryArticle()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', '/m/acme/new/article');

        $this->assertSelectorExists('form[name=entry_article]');
    }

    public function testUserCanCreateEntryArticleFromMagazinePage()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/new/article');

        $this->client->submit(
            $crawler->filter('form[name=entry_article]')->selectButton('Add new thread')->form(
                [
                    'entry_article[title]' => 'Test entry 1',
                    'entry_article[body]' => 'Test body',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/threads/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
    }

    public function testUserCanCreateEntryPhoto()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', '/m/acme/new/photo');

        $this->assertSelectorExists('form[name=entry_image]');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCanCreateEntryPhotoFromMagazinePage()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');
        $repository = $this->entryRepository;

        $crawler = $this->client->request('GET', '/m/acme/new/photo');

        $this->assertSelectorExists('form[name=entry_image]');

        $form = $crawler->filter('#main form[name=entry_image]')->selectButton('Add new photo')->form([
            'entry_image[title]' => 'Test image 1',
            'entry_image[image]' => $this->kibbyPath,
        ]);
        // Needed since we require this global to be set when validating entries but the client doesn't actually set it
        $_FILES = $form->getPhpFiles();
        $this->client->submit($form);

        $this->assertResponseRedirects('/m/acme/threads/newest');

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test image 1');
        $this->assertSelectorExists('figure img');
        $imgSrc = $crawler->filter('figure img.thumb-subject')->getNode(0)->attributes->getNamedItem('src')->textContent;
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $imgSrc);

        $user = $this->getUserByUsername('user');
        $entry = $repository->findOneBy(['user' => $user]);
        $this->assertNotNull($entry);
        $this->assertNotNull($entry->image);
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $entry->image->filePath);
        $_FILES = [];
    }

    public function testUserCanCreateEntryArticleForAdults()
    {
        $this->client->loginUser($this->getUserByUsername('user', hideAdult: false));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/new/article');

        $this->client->submit(
            $crawler->filter('form[name=entry_article]')->selectButton('Add new thread')->form(
                [
                    'entry_article[title]' => 'Test entry 1',
                    'entry_article[body]' => 'Test body',
                    'entry_article[isAdult]' => '1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/threads/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
        $this->assertSelectorTextContains('article h2 .danger', '18+');
    }
}

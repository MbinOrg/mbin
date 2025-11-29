<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class EntryCreateControllerTest extends WebTestCase
{
    public string $kibbyPath;

    public function setUp(): void
    {
        parent::setUp();
        $this->kibbyPath = \dirname(__FILE__, 4).'/assets/kibby_emoji.png';
    }

    public function testUserCanCreateEntry()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->client->request('GET', '/m/acme/new_entry');

        $this->assertSelectorExists('form[name=entry]');
    }

    public function testUserCanCreateEntryLinkFromMagazinePage(): void
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/new_entry');

        $this->client->submit(
            $crawler->filter('form[name=entry]')->selectButton('Add new thread')->form(
                [
                    'entry[url]' => 'https://kbin.pub',
                    'entry[title]' => 'Test entry 1',
                    'entry[body]' => 'Test body',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/default/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
    }

    public function testUserCanCreateEntryArticleFromMagazinePage()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET', '/m/acme/new_entry');

        $this->client->submit(
            $crawler->filter('form[name=entry]')->selectButton('Add new thread')->form(
                [
                    'entry[title]' => 'Test entry 1',
                    'entry[body]' => 'Test body',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/default/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCanCreateEntryPhotoFromMagazinePage()
    {
        $this->client->loginUser($this->getUserByUsername('user'));

        $this->getMagazineByName('acme');
        $repository = $this->entryRepository;

        $crawler = $this->client->request('GET', '/m/acme/new_entry');

        $this->assertSelectorExists('form[name=entry]');

        $form = $crawler->filter('#main form[name=entry]')->selectButton('Add new thread')->form([
            'entry[title]' => 'Test image 1',
            'entry[image]' => $this->kibbyPath,
        ]);
        // Needed since we require this global to be set when validating entries but the client doesn't actually set it
        $_FILES = $form->getPhpFiles();
        $this->client->submit($form);

        $this->assertResponseRedirects('/m/acme/default/newest');

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

        $crawler = $this->client->request('GET', '/m/acme/new_entry');

        $this->client->submit(
            $crawler->filter('form[name=entry]')->selectButton('Add new thread')->form(
                [
                    'entry[title]' => 'Test entry 1',
                    'entry[body]' => 'Test body',
                    'entry[isAdult]' => '1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/default/newest');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'Test entry 1');
        $this->assertSelectorTextContains('article h2 .danger', '18+');
    }

    public function testPresetValues()
    {
        $this->client->loginUser($this->getUserByUsername('user', hideAdult: false));

        $this->getMagazineByName('acme');

        $crawler = $this->client->request('GET',
            '/m/acme/new_entry?'
                .'title=test'
                .'&url='.urlencode('https://example.com#title')
                .'&body='.urlencode("**Test**\nbody")
                .'&imageAlt=alt'
                .'&isNsfw=1'
                .'&isOc=1'
                .'&tags[]=1&tags[]=2'
        );

        $this->assertFormValue('form[name=entry]', 'entry[title]', 'test');
        $this->assertFormValue('form[name=entry]', 'entry[url]', 'https://example.com#title');
        $this->assertFormValue('form[name=entry]', 'entry[body]', "**Test**\nbody");
        $this->assertFormValue('form[name=entry]', 'entry[imageAlt]', 'alt');
        $this->assertFormValue('form[name=entry]', 'entry[isAdult]', '1');
        $this->assertFormValue('form[name=entry]', 'entry[isOc]', '1');
        $this->assertFormValue('form[name=entry]', 'entry[tags]', '1 2');
    }

    public function testPresetImage()
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $magazine = $this->getMagazineByName('acme');

        $imgEntry = $this->createEntry('img', $magazine, $user, imageDto: $this->getKibbyImageDto());
        $imgHash = strtok($imgEntry->image->fileName, '.');

        // this  is necessary so the second entry is guaranteed to be newer than the first
        sleep(1);

        $crawler = $this->client->request('GET',
            '/m/acme/new_entry?'
            .'title=test'
            .'&imageHash='.$imgHash
        );

        $this->client->submit($crawler->filter('form[name=entry]')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('article h2', 'test');
        $this->assertSelectorExists('figure img');
        $imgSrc = $crawler->filter('figure img.thumb-subject')->getNode(0)->attributes->getNamedItem('src')->textContent;
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $imgSrc);
    }

    public function testPresetImageNotFound()
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $magazine = $this->getMagazineByName('acme');

        $imgEntry = $this->createEntry('img', $magazine, $user, imageDto: $this->getKibbyImageDto());
        $imgHash = strtok($imgEntry->image->fileName, '.');
        $imgHash = substr($imgHash, 0, \strlen($imgHash) - 1).'0';

        // this  is necessary so the second entry is guaranteed to be newer than the first
        sleep(1);

        $crawler = $this->client->request('GET',
            '/m/acme/new_entry?'
            .'title=test'
            .'&imageHash='.$imgHash
        );

        $this->assertSelectorTextContains('.alert.alert__danger', 'The image referenced by \'imageHash\' could not be found.');
    }
}

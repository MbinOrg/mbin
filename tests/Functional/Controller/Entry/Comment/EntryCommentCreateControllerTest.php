<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class EntryCommentCreateControllerTest extends WebTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->kibbyPath = \dirname(__FILE__, 5).'/assets/kibby_emoji.png';
    }

    public function testUserCanCreateEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $this->client->submit(
            $crawler->filter('form[name=entry_comment]')->selectButton('Add comment')->form(
                [
                    'entry_comment[body]' => 'test comment 1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/t/'.$entry->getId().'/test-entry-1');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main blockquote', 'test comment 1');
    }

    public function testUserCannotCreateEntryCommentInLockedEntry(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');
        $this->entryManager->toggleLock($entry, $user);

        $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        self::assertSelectorTextNotContains('#main', 'Add comment');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCanCreateEntryCommentWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $form = $crawler->filter('form[name=entry_comment]')->selectButton('entry_comment[submit]')->form();
        $form->get('entry_comment[body]')->setValue('test comment 1');
        $form->get('entry_comment[image]')->upload($this->kibbyPath);
        // Needed since we require this global to be set when validating entries but the client doesn't actually set it
        $_FILES = $form->getPhpFiles();
        $this->client->submit($form);

        $this->assertResponseRedirects('/m/acme/t/'.$entry->getId().'/test-entry-1');
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main blockquote', 'test comment 1');
        $this->assertSelectorExists('blockquote footer figure img');
        $imgSrc = $crawler->filter('blockquote footer figure img')->getNode(0)->attributes->getNamedItem('src')->textContent;
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $imgSrc);
        $_FILES = [];
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCanReplyEntryComment(): void
    {
        $comment = $this->createEntryComment(
            'test comment 1',
            $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub'),
            $this->getUserByUsername('JaneDoe')
        );

        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");
        $crawler = $this->client->click($crawler->filter('#entry-comment-'.$comment->getId())->selectLink('Reply')->link());

        $this->assertSelectorTextContains('#main blockquote', 'test comment 1');

        $crawler = $this->client->submit(
            $crawler->filter('form[name=entry_comment]')->selectButton('Add comment')->form(
                [
                    'entry_comment[body]' => 'test comment 2',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/t/'.$entry->getId().'/test-entry-1');
        $crawler = $this->client->followRedirect();

        $this->assertEquals(2, $crawler->filter('#main blockquote')->count());
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCantCreateInvalidEntryComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $entry = $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $crawler = $this->client->request('GET', "/m/acme/t/{$entry->getId()}/test-entry-1");

        $this->client->submit(
            $crawler->filter('form[name=entry_comment]')->selectButton('Add comment')->form(
                [
                    'entry_comment[body]' => '',
                ]
            )
        );

        $this->assertSelectorTextContains(
            '#content',
            'This value should not be blank.'
        );
    }
}

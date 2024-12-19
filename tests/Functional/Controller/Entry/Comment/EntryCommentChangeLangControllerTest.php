<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\Tests\WebTestCase;

class EntryCommentChangeLangControllerTest extends WebTestCase
{
    public function testModCanChangeLanguage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createEntryComment('test comment 1');

        $crawler = $this->client->request('GET', "/m/acme/t/{$comment->entry->getId()}/-/comment/{$comment->getId()}/moderate");

        $form = $crawler->filter('.moderate-panel')->selectButton('lang[submit]')->form();

        $this->assertSame($form['lang']['lang']->getValue(), 'en');

        $form['lang']['lang']->select('fr');

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .badge-lang', 'French');
    }
}

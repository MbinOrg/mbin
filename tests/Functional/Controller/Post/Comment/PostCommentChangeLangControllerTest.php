<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;

class PostCommentChangeLangControllerTest extends WebTestCase
{
    public function testModCanChangeLanguage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createPostComment('test comment 1');

        $crawler = $this->client->request('GET', "/m/acme/p/{$comment->post->getId()}/-/reply/{$comment->getId()}/moderate");

        $form = $crawler->filter('.moderate-panel')->selectButton('Change language')->form();

        $this->assertSame($form['lang']['lang']->getValue(), 'en');

        $form['lang']['lang']->select('fr');

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .badge-lang', 'French');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\Tests\WebTestCase;

class EntryCommentModerateControllerTest extends WebTestCase
{
    public function testModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createEntryComment('test comment 1');

        $crawler = $this->client->request('get', "/m/{$comment->magazine->name}/t/{$comment->entry->getId()}");
        $this->client->click($crawler->filter('#entry-comment-'.$comment->getId())->selectLink('Moderate')->link());

        $this->assertSelectorTextContains('.moderate-panel', 'Ban');
    }

    public function testXmlModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createEntryComment('test comment 1');

        $crawler = $this->client->request('get', "/m/{$comment->magazine->name}/t/{$comment->entry->getId()}");
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('#entry-comment-'.$comment->getId())->selectLink('Moderate')->link());

        $this->assertStringContainsString('moderate-panel', $this->client->getResponse()->getContent());
    }

    public function testUnauthorizedCanNotShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $comment = $this->createEntryComment('test comment 1');

        $this->client->request('get', "/m/{$comment->magazine->name}/t/{$comment->entry->getId()}");
        $this->assertSelectorTextNotContains('#entry-comment-'.$comment->getId(), 'Moderate');

        $this->client->request(
            'get',
            "/m/{$comment->magazine->name}/t/{$comment->entry->getId()}/-/comment/{$comment->getId()}/moderate"
        );

        $this->assertResponseStatusCodeSame(403);
    }
}

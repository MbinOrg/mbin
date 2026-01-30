<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;

class PostCommentModerateControllerTest extends WebTestCase
{
    public function testModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createPostComment('test comment 1');

        $crawler = $this->client->request('get', "/m/{$comment->magazine->name}/p/{$comment->post->getId()}");
        $this->client->click($crawler->filter('#post-comment-'.$comment->getId())->selectLink('Moderate')->link());

        $this->assertSelectorTextContains('.moderate-panel', 'ban');
    }

    public function testXmlModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $comment = $this->createPostComment('test comment 1');

        $crawler = $this->client->request('get', "/m/{$comment->magazine->name}/p/{$comment->post->getId()}");
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('#post-comment-'.$comment->getId())->selectLink('Moderate')->link());

        $this->assertStringContainsString('moderate-panel', $this->client->getResponse()->getContent());
    }

    public function testUnauthorizedCanNotShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $comment = $this->createPostComment('test comment 1');

        $this->client->request('get', "/m/{$comment->magazine->name}/p/{$comment->post->getId()}");
        $this->assertSelectorTextNotContains('#post-comment-'.$comment->getId(), 'moderate');

        $this->client->request(
            'get',
            "/m/{$comment->magazine->name}/p/{$comment->post->getId()}/-/reply/{$comment->getId()}/moderate"
        );

        $this->assertResponseStatusCodeSame(403);
    }
}

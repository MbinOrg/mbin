<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostModerateControllerTest extends WebTestCase
{
    public function testModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('get', '/microblog');
        $this->client->click($crawler->filter('#post-'.$post->getId())->selectLink('Moderate')->link());

        $this->assertSelectorTextContains('.moderate-panel', 'ban');
    }

    public function testXmlModCanShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('get', '/microblog');
        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->click($crawler->filter('#post-'.$post->getId())->selectLink('Moderate')->link());

        $this->assertStringContainsString('moderate-panel', $this->client->getResponse()->getContent());
    }

    public function testUnauthorizedCanNotShowPanel(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $post = $this->createPost('test post 1');

        $this->client->request('get', "/m/{$post->magazine->name}/p/{$post->getId()}");
        $this->assertSelectorTextNotContains('#post-'.$post->getId(), 'Moderate');

        $this->client->request(
            'get',
            "/m/{$post->magazine->name}/p/{$post->getId()}/-/moderate"
        );

        $this->assertResponseStatusCodeSame(403);
    }
}

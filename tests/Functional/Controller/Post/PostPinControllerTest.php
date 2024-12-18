<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostPinControllerTest extends WebTestCase
{
    public function testModCanPinEntry(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost(
            'test post 1',
            $this->getMagazineByName('acme'),
        );

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/-/moderate");

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Pin')->form([]));
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('#main .post .fa-thumbtack');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unpin')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .post .fa-thumbtack');
    }
}

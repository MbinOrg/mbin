<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostBoostControllerTest extends WebTestCase
{
    public function testLoggedUserBoostPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1', null, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $this->client->submit(
            $crawler->filter('#main .post')->selectButton('Boost')->form([])
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .post', 'Boost (1)');

        $this->client->click($crawler->filter('#activity')->selectLink('Boosts (1)')->link());

        $this->assertSelectorTextContains('#main .users-columns', 'JohnDoe');
    }
}

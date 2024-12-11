<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostChangeAdultControllerTest extends WebTestCase
{
    public function testModCanMarkAsAdultContent(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/-/moderate");
        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Mark NSFW')->form([
                'adult' => 'on',
            ])
        );
        $this->client->followRedirect();
        $this->assertSelectorTextContains('#main .post .badge', '18+');

        $this->client->submit(
            $crawler->filter('.moderate-panel')->selectButton('Mark NSFW')->form([
                'adult' => false,
            ])
        );
        $this->client->followRedirect();
        $this->assertSelectorTextNotContains('#main .post', '18+');
    }
}

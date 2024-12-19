<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;

class PostCommentBoostControllerTest extends WebTestCase
{
    public function testLoggedUserBoostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1', null, $this->getUserByUsername('JaneDoe'));
        $comment = $this->createPostComment('test comment 1', $post, $this->getUserByUsername('JaneDoe'));

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $crawler = $this->client->submit(
            $crawler->filter("#post-comment-{$comment->getId()}")->selectButton('Boost')->form()
        );

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        // $this->assertSelectorTextContains("#post-comment-{$comment->getId()}", 'Boost (1)');

        $crawler = $this->client->click($crawler->filter("#post-comment-{$comment->getId()}")->selectLink('Activity')->link());

        $this->assertSelectorTextContains('#main #activity', 'Boosts (1)');
        $this->client->click($crawler->filter('#main #activity')->selectLink('Boosts (1)')->link());

        $this->assertSelectorTextContains('#main .users-columns', 'JohnDoe');
    }
}

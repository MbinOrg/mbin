<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;

class PostCommentEditControllerTest extends WebTestCase
{
    public function testAuthorCanEditOwnPostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');
        $this->createPostComment('test comment 1', $post);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $crawler = $this->client->click($crawler->filter('#main .post-comment')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .post-comment');
        $this->assertSelectorTextContains('textarea[name="post_comment[body]"]', 'test comment 1');

        $this->client->submit(
            $crawler->filter('form[name=post_comment]')->selectButton('Save changes')->form(
                [
                    'post_comment[body]' => 'test comment 2 body',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .post-comment', 'test comment 2 body');
    }

    public function testAuthorCanEditOwnPostCommentWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');
        $this->createPostComment('test comment 1', $post, imageDto: $this->getKibbyImageDto());

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $crawler = $this->client->click($crawler->filter('#main .post-comment')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .post-comment');
        $this->assertSelectorTextContains('textarea[name="post_comment[body]"]', 'test comment 1');
        $this->assertSelectorExists('#main .post-comment img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $node->attributes->getNamedItem('src')->textContent);

        $this->client->submit(
            $crawler->filter('form[name=post_comment]')->selectButton('Save changes')->form(
                [
                    'post_comment[body]' => 'test comment 2 body',
                ]
            )
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .post-comment', 'test comment 2 body');
        $this->assertSelectorExists('#main .post-comment img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $node->attributes->getNamedItem('src')->textContent);
    }
}

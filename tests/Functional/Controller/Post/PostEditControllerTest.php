<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class PostEditControllerTest extends WebTestCase
{
    public function testAuthorCanEditOwnPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');
        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $crawler = $this->client->click($crawler->filter('#main .post')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .post');
        $this->assertSelectorTextContains('#post_body', 'test post 1');
        //        $this->assertEquals('disabled', $crawler->filter('#post_magazine_autocomplete')->attr('disabled')); @todo

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Edit post')->form(
                [
                    'post[body]' => 'test post 2 body',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .post .content', 'test post 2 body');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testAuthorCanEditOwnPostWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $imageDto = $this->getKibbyImageDto();
        $post = $this->createPost('test post 1', imageDto: $imageDto);
        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1");

        $crawler = $this->client->click($crawler->filter('#main .post')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .post');
        $this->assertSelectorTextContains('#post_body', 'test post 1');
        //        $this->assertEquals('disabled', $crawler->filter('#post_magazine_autocomplete')->attr('disabled')); @todo
        $this->assertSelectorExists('#main .post img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Edit post')->form(
                [
                    'post[body]' => 'test post 2 body',
                ]
            )
        );

        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#main .post .content', 'test post 2 body');
        $this->assertSelectorExists('#main .post img');
        $node = $crawler->selectImage('kibby')->getNode(0);
        $this->assertNotNull($node);
        $this->assertStringContainsString($imageDto->filePath, $node->attributes->getNamedItem('src')->textContent);
    }

    public function testAuthorCanEditPostToMarkItIsForAdults(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');
        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1/edit");

        $crawler = $this->client->click($crawler->filter('#main .post')->selectLink('Edit')->link());

        $this->assertSelectorExists('#main .post');

        $this->client->submit(
            $crawler->filter('form[name=post]')->selectButton('Edit post')->form(
                [
                    'post[isAdult]' => '1',
                ]
            )
        );

        $this->client->followRedirect();

        $this->assertSelectorTextContains('blockquote header .danger', '18+');
    }
}

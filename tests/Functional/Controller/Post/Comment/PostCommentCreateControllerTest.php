<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post\Comment;

use App\Tests\WebTestCase;
use PHPUnit\Framework\Attributes\Group;

class PostCommentCreateControllerTest extends WebTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->kibbyPath = \dirname(__FILE__, 5).'/assets/kibby_emoji.png';
    }

    public function testUserCanCreatePostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('GET', '/m/acme/p/'.$post->getId().'/test-post-1/reply');

        $this->client->submit(
            $crawler->filter('form[name=post_comment]')->selectButton('Add comment')->form(
                [
                    'post_comment[body]' => 'test comment 1',
                ]
            )
        );

        $this->assertResponseRedirects('/m/acme/p/'.$post->getId().'/test-post-1');
        $this->client->followRedirect();

        $this->assertSelectorTextContains('#comments .content', 'test comment 1');
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCanCreatePostCommentWithImage(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/test-post-1/reply");

        $form = $crawler->filter('form[name=post_comment]')->selectButton('Add comment')->form();
        $form->get('post_comment[body]')->setValue('Test comment 1');
        $form->get('post_comment[image]')->upload($this->kibbyPath);
        // Needed since we require this global to be set when validating entries but the client doesn't actually set it
        $_FILES = $form->getPhpFiles();
        $this->client->submit($form);

        $this->assertResponseRedirects("/m/acme/p/{$post->getId()}/test-post-1");
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('#comments .content', 'Test comment 1');
        $this->assertSelectorExists('#comments footer figure img');
        $imgSrc = $crawler->filter('#comments footer figure img')->getNode(0)->attributes->getNamedItem('src')->textContent;
        $this->assertStringContainsString(self::KIBBY_PNG_URL_RESULT, $imgSrc);
        $_FILES = [];
    }

    #[Group(name: 'NonThreadSafe')]
    public function testUserCannotCreateInvalidPostComment(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1');

        $crawler = $this->client->request('GET', '/m/acme/p/'.$post->getId().'/test-post-1/reply');

        $crawler = $this->client->submit(
            $crawler->filter('form[name=post_comment]')->selectButton('Add comment')->form(
                [
                    'post_comment[body]' => '',
                ]
            )
        );

        $this->assertSelectorTextContains('#content', 'This value should not be blank.');
    }
}

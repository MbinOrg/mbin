<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Magazine\Panel;

use App\Tests\WebTestCase;

class MagazineTrashControllerTest extends WebTestCase
{
    public function testModCanSeeEntryInTrash(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getMagazineByName('acme');

        $entry = $this->getEntryByTitle(
            'Test entry 1',
            'https://kbin.pub',
            null,
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request('GET', '/m/acme/t/'.$entry->getId().'/test-entry-1/moderate');
        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Delete')->form([]));

        $this->client->request('GET', '/m/acme/panel/trash');
        $this->assertSelectorTextContains('#main .options__main a.active', 'Trash');
        $this->assertSelectorTextContains('#main .entry', 'Test entry 1');
    }

    public function testModCanSeeEntryCommentInTrash(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getMagazineByName('acme');

        $comment = $this->createEntryComment(
            'Test comment 1',
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request(
            'GET',
            '/m/acme/t/'.$comment->entry->getId().'/test-entry-1/comment/'.$comment->getId().'/moderate'
        );
        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Delete')->form([]));

        $this->client->request('GET', '/m/acme/panel/trash');
        $this->assertSelectorTextContains('#main .comment', 'Test comment 1');
    }

    public function testModCanSeePostInTrash(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getMagazineByName('acme');

        $post = $this->createPost(
            'Test post 1',
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request(
            'GET',
            '/m/acme/p/'.$post->getId().'/-/moderate'
        );
        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Delete')->form([]));

        $this->client->request('GET', '/m/acme/panel/trash');
        $this->assertSelectorTextContains('#main .post', 'Test post 1');
    }

    public function testModCanSeePostCommentInTrash(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));
        $this->getMagazineByName('acme');

        $comment = $this->createPostComment(
            'Test comment 1',
            null,
            $this->getUserByUsername('JaneDoe')
        );

        $crawler = $this->client->request(
            'GET',
            '/m/acme/p/'.$comment->post->getId().'/test-entry-1/reply/'.$comment->getId().'/moderate'
        );
        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Delete')->form([]));

        $this->client->request('GET', '/m/acme/panel/trash');
        $this->assertSelectorTextContains('#main .comment', 'Test comment 1');
    }

    public function testUnauthorizedUserCannotSeeTrash(): void
    {
        $this->client->loginUser($this->getUserByUsername('JaneDoe'));

        $this->getMagazineByName('acme');

        $this->client->request('GET', '/m/acme/panel/trash');

        $this->assertResponseStatusCodeSame(403);
    }
}

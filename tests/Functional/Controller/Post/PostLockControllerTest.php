<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\Tests\WebTestCase;

class PostLockControllerTest extends WebTestCase
{
    public function testModCanLockPost(): void
    {
        $this->client->loginUser($this->getUserByUsername('JohnDoe'));

        $post = $this->createPost('test post 1', $this->getMagazineByName('acme'));

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/-/moderate");

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Lock')->form([]));
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('#main .post footer span .fa-lock');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .post footer span .fa-lock');
    }

    public function testAuthorCanLockEntry(): void
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $post = $this->createPost('test post 1', $this->getMagazineByName('acme'), user: $user);
        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}");
        $this->assertSelectorExists('#main .post footer .dropdown .fa-lock');

        $this->client->submit($crawler->filter('#main .post footer .dropdown')->selectButton('Lock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorExists('#main .post footer span .fa-lock');
    }

    public function testModCanUnlockPost(): void
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->client->loginUser($user);

        $post = $this->createPost('test post 1', $this->getMagazineByName('acme'));
        $this->postManager->toggleLock($post, $user);

        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}/-/moderate");
        $this->assertSelectorExists('#main .post footer span .fa-lock');

        $this->client->submit($crawler->filter('#main .moderate-panel')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .post footer span .fa-lock');
    }

    public function testAuthorCanUnlockEntry(): void
    {
        $user = $this->getUserByUsername('user');
        $this->client->loginUser($user);

        $post = $this->createPost('test post 1', $this->getMagazineByName('acme'), user: $user);
        $this->postManager->toggleLock($post, $user);
        $crawler = $this->client->request('GET', "/m/acme/p/{$post->getId()}");
        $this->assertSelectorExists('#main .post footer .dropdown .fa-lock-open');

        $this->client->submit($crawler->filter('#main .post footer .dropdown')->selectButton('Unlock')->form([]));
        $this->client->followRedirect();
        $this->assertSelectorNotExists('#main .post footer span .fa-lock');
    }
}

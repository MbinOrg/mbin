<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\User;

use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class UserFrontControllerTest extends WebTestCase
{
    public function testOverview(): void
    {
        $this->client = $this->prepareEntries();

        $crawler = $this->client->request('GET', '/u/JohnDoe');

        $this->assertSelectorTextContains('.options.options .active', 'Overview');
        $this->assertEquals(2, $crawler->filter('#main .entry')->count());
        $this->assertEquals(2, $crawler->filter('#main .entry-comment')->count());
        $this->assertEquals(2, $crawler->filter('#main .post')->count());
        $this->assertEquals(2, $crawler->filter('#main .post-comment')->count());
    }

    public function testThreadsPage(): void
    {
        $this->client = $this->prepareEntries();

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('Threads')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Threads (1)');
        $this->assertEquals(1, $crawler->filter('#main .entry')->count());
    }

    public function testCommentsPage(): void
    {
        $this->client = $this->prepareEntries();

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $this->client->click($crawler->filter('#main .options')->selectLink('Comments')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Comments (2)');
        $this->assertEquals(2, $crawler->filter('#main .entry-comment')->count());
    }

    public function testPostsPage(): void
    {
        $this->client = $this->prepareEntries();

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('Posts')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Posts (1)');
        $this->assertEquals(1, $crawler->filter('#main .post')->count());
    }

    public function testRepliesPage(): void
    {
        $this->client = $this->prepareEntries();

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('Replies')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Replies (2)');
        $this->assertEquals(2, $crawler->filter('#main .post-comment')->count());
        $this->assertEquals(2, $crawler->filter('#main .post')->count());
    }

    public function createSubscriptionsPage()
    {
        $user = $this->getUserByUsername('JohnDoe');
        $this->getMagazineByName('kbin');
        $this->getMagazineByName('mag', $this->getUserByUsername('JaneDoe'));

        $manager = $this->magazineManager;
        $manager->subscribe($this->getMagazineByName('mag'), $user);

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('subscriptions')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'subscriptions (2)');
        $this->assertEquals(2, $crawler->filter('#main .magazines ul li')->count());
    }

    public function testFollowersPage(): void
    {
        $user1 = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JaneDoe');

        $manager = $this->userManager;
        $manager->follow($user2, $user1);

        $this->client->loginUser($user1);

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('Followers')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Followers (1)');
        $this->assertEquals(1, $crawler->filter('#main .users ul li')->count());
    }

    public function testFollowingPage(): void
    {
        $user1 = $this->getUserByUsername('JohnDoe');
        $user2 = $this->getUserByUsername('JaneDoe');

        $manager = $this->userManager;
        $manager->follow($user1, $user2);

        $this->client->loginUser($user1);

        $crawler = $this->client->request('GET', '/u/JohnDoe');
        $crawler = $this->client->click($crawler->filter('#main .options')->selectLink('Following')->link());

        $this->assertSelectorTextContains('.options.options--top .active', 'Following (1)');
        $this->assertEquals(1, $crawler->filter('#main .users ul li')->count());
    }

    private function prepareEntries(): KernelBrowser
    {
        $entry1 = $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            $this->getMagazineByName('mag', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );
        $entry2 = $this->getEntryByTitle(
            'test entry 2',
            'https://kbin.pub',
            null,
            $this->getMagazineByName('mag', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );
        $entry3 = $this->getEntryByTitle('test entry 3', 'https://kbin.pub');

        $this->createEntryComment('test entry comment 1', $entry1);
        $this->createEntryComment('test entry comment 2', $entry2, $this->getUserByUsername('JaneDoe'));
        $this->createEntryComment('test entry comment 3', $entry3);

        $post1 = $this->createPost(
            'test post 1',
            $this->getMagazineByName('mag', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );
        $post2 = $this->createPost(
            'test post 2',
            $this->getMagazineByName('mag', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );
        $post3 = $this->createPost('test post 3');

        $this->createPostComment('test post comment 1', $post1);
        $this->createPostComment('test post comment 2', $post2, $this->getUserByUsername('JaneDoe'));
        $this->createPostComment('test post comment 3', $post3);

        return $this->client;
    }
}

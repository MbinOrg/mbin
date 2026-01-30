<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry\Comment;

use App\DTO\ModeratorDto;
use App\Service\MagazineManager;
use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class EntryCommentFrontControllerTest extends WebTestCase
{
    public function testFrontPage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/comments');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/comments/newest');

        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextContains('blockquote header', 'to kbin in test entry 2');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 3');

        $this->assertcount(3, $crawler->filter('.comment'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testMagazinePage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/m/acme/comments');
        $this->assertSelectorTextContains('h2', 'Hot');

        $crawler = $this->client->request('GET', '/m/acme/comments/newest');

        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextNotContains('blockquote header', 'to acme');
        $this->assertSelectorTextContains('blockquote header', 'in test entry 1');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 2');

        $this->assertSelectorTextContains('.head-title', '/m/acme');
        $this->assertSelectorTextContains('#sidebar .magazine', 'acme');

        $this->assertcount(2, $crawler->filter('.comment'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', 'acme');
            $this->assertSelectorTextContains('h2', ucfirst($sortOption));
        }
    }

    public function testSubPage(): void
    {
        $this->client = $this->prepareEntries();

        $magazineManager = $this->client->getContainer()->get(MagazineManager::class);
        $magazineManager->subscribe($this->getMagazineByName('acme'), $this->getUserByUsername('Actor'));

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/sub/comments');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/sub/comments/newest');

        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextContains('blockquote header', 'to acme in test entry 1');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 2');

        $this->assertSelectorTextContains('.head-title', '/sub');

        $this->assertcount(2, $crawler->filter('.comment'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testModPage(): void
    {
        $this->client = $this->prepareEntries();
        $admin = $this->getUserByUsername('admin', isAdmin: true);

        $magazineManager = $this->client->getContainer()->get(MagazineManager::class);
        $moderator = new ModeratorDto($this->getMagazineByName('acme'));
        $moderator->user = $this->getUserByUsername('Actor');
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/mod/comments');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/mod/comments/newest');

        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextContains('blockquote header', 'to acme in test entry 1');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 2');

        $this->assertSelectorTextContains('.head-title', '/mod');

        $this->assertcount(2, $crawler->filter('.comment'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testFavPage(): void
    {
        $this->client = $this->prepareEntries();

        $favouriteManager = $this->favouriteManager;
        $favouriteManager->toggle(
            $this->getUserByUsername('Actor'),
            $this->createEntryComment('test comment 1', $this->getEntryByTitle('test entry 1'))
        );

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/fav/comments');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/fav/comments/newest');

        $this->assertSelectorTextContains('blockquote header', 'JohnDoe');
        $this->assertSelectorTextContains('blockquote header', 'to acme in test entry 1');
        $this->assertSelectorTextContains('blockquote .content', 'test comment 1');

        $this->assertcount(1, $crawler->filter('.comment'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    private function prepareEntries(): KernelBrowser
    {
        $this->createEntryComment(
            'test comment 1',
            $this->getEntryByTitle('test entry 1', 'https://kbin.pub'),
            $this->getUserByUsername('JohnDoe')
        );
        $this->createEntryComment(
            'test comment 2',
            $this->getEntryByTitle('test entry 1', 'https://kbin.pub'),
            $this->getUserByUsername('JohnDoe')
        );
        $this->createEntryComment(
            'test comment 3',
            $this->getEntryByTitle('test entry 2', 'https://kbin.pub', null, $this->getMagazineByName('kbin')),
            $this->getUserByUsername('JohnDoe')
        );

        return $this->client;
    }

    private function getSortOptions(): array
    {
        return ['Hot', 'Newest', 'Active', 'Oldest'];
    }
}

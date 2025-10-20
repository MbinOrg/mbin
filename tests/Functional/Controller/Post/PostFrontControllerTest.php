<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Post;

use App\DTO\ModeratorDto;
use App\Enums\ESortOptions;
use App\Service\MagazineManager;
use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class PostFrontControllerTest extends WebTestCase
{
    public function testFrontPage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/microblog');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/microblog/newest');

        $this->assertSelectorTextContains('.post header', 'JohnDoe');
        $this->assertSelectorTextContains('.post header', 'to acme');

        $this->assertSelectorTextContains('#header .active', 'Microblog');

        $this->assertcount(2, $crawler->filter('.post'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testMagazinePage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/m/acme/microblog');
        $this->assertSelectorTextContains('h2', 'Hot');

        $crawler = $this->client->request('GET', '/m/acme/microblog/newest');

        $this->assertSelectorTextContains('.post header', 'JohnDoe');
        $this->assertSelectorTextNotContains('.post header', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/m/acme');
        $this->assertSelectorTextContains('#sidebar .magazine', 'acme');

        $this->assertSelectorTextContains('#header .active', 'Microblog');

        $this->assertcount(1, $crawler->filter('.post'));

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

        $magazineManager = $this->magazineManager;
        $magazineManager->subscribe($this->getMagazineByName('acme'), $this->getUserByUsername('Actor'));

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/sub/microblog');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/sub/microblog/newest');

        $this->assertSelectorTextContains('.post header', 'JohnDoe');
        $this->assertSelectorTextContains('.post header', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/sub');

        $this->assertSelectorTextContains('#header .active', 'Microblog');

        $this->assertcount(1, $crawler->filter('.post'));

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

        $this->client->request('GET', '/mod/microblog');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/mod/microblog/newest');

        $this->assertSelectorTextContains('.post header', 'JohnDoe');
        $this->assertSelectorTextContains('.post header', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/mod');

        $this->assertSelectorTextContains('#header .active', 'Microblog');

        $this->assertcount(1, $crawler->filter('.post'));

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
        $favouriteManager->toggle($this->getUserByUsername('Actor'), $this->createPost('test post 3'));

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/fav/microblog');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/fav/microblog/newest');

        $this->assertSelectorTextContains('.post header', 'JohnDoe');
        $this->assertSelectorTextContains('.post header', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/fav');

        $this->assertSelectorTextContains('#header .active', 'Microblog');

        $this->assertcount(1, $crawler->filter('.post'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testCustomDefaultSort(): void
    {
        $older = $this->createPost('Older entry');
        $older->createdAt = new \DateTimeImmutable('now - 1 day');
        $older->updateRanking();
        $this->entityManager->flush();
        $newer = $this->createPost('Newer entry');
        $comment = $this->createPostComment('someone was here', post: $older);
        self::assertGreaterThan($older->getRanking(), $newer->getRanking());

        $user = $this->getUserByUsername('user');
        $user->frontDefaultSort = ESortOptions::Newest->value;
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/microblog');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.options__filter button', $this->translator->trans(ESortOptions::Newest->value));

        $iterator = $crawler->filter('#content div')->children()->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-{$newer->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-{$older->getId()}", $secondId);

        $user->frontDefaultSort = ESortOptions::Commented->value;
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/microblog');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.options__filter button', $this->translator->trans(ESortOptions::Commented->value));

        $children = $crawler->filter('#content div')->children();
        $iterator = $children->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-{$older->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("post-{$newer->getId()}", $secondId);
    }

    private function prepareEntries(): KernelBrowser
    {
        $this->createPost(
            'test post 1',
            $this->getMagazineByName('kbin', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );

        // sleep so the creation time is actually 1 second apart for the sort to reliably be the same
        sleep(1);

        $this->createPost('test post 2');

        return $this->client;
    }

    private function getSortOptions(): array
    {
        return ['Top', 'Hot', 'Newest', 'Active', 'Commented'];
    }
}

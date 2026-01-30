<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Entry;

use App\DTO\ModeratorDto;
use App\Enums\ESortOptions;
use App\Service\MagazineManager;
use App\Tests\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class EntryFrontControllerTest extends WebTestCase
{
    public function testRootPage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to acme');

        $this->assertcount(2, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testXmlRootPage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testXmlRootPageIsFrontPage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/');

        $root_content = self::removeTimeElements($this->clearTokens($this->client->getResponse()->getContent()));

        $this->client->request('GET', '/all');
        $frontContent = self::removeTimeElements($this->clearTokens($this->client->getResponse()->getContent()));

        $this->assertSame($root_content, $frontContent);
    }

    public function testFrontPage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/all');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/all/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to acme');

        $this->assertcount(2, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testXmlFrontPage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/all');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testMagazinePage(): void
    {
        $this->client = $this->prepareEntries();

        $this->client->request('GET', '/m/acme');
        $this->assertSelectorTextContains('h2', 'Hot');

        $this->client->request('GET', '/m/ACME');
        $this->assertSelectorTextContains('h2', 'Hot');

        $crawler = $this->client->request('GET', '/m/acme/threads/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextNotContains('.entry__meta', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/m/acme');
        $this->assertSelectorTextContains('#sidebar .magazine', 'acme');

        $this->assertSelectorTextContains('#header .active', 'Threads');

        $this->assertcount(1, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', 'acme');
            $this->assertSelectorTextContains('h2', ucfirst($sortOption));
        }
    }

    public function testXmlMagazinePage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/m/acme/newest');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testSubPage(): void
    {
        $this->client = $this->prepareEntries();

        $magazineManager = $this->client->getContainer()->get(MagazineManager::class);
        $magazineManager->subscribe($this->getMagazineByName('acme'), $this->getUserByUsername('Actor'));

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/sub');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/sub/threads/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/sub');

        $this->assertSelectorTextContains('#header .active', 'Threads');

        $this->assertcount(1, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testXmlSubPage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $magazineManager = $this->client->getContainer()->get(MagazineManager::class);
        $magazineManager->subscribe($this->getMagazineByName('acme'), $this->getUserByUsername('Actor'));

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/sub');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
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

        $this->client->request('GET', '/mod');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/mod/threads/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JohnDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to acme');

        $this->assertSelectorTextContains('.head-title', '/mod');

        $this->assertSelectorTextContains('#header .active', 'Threads');

        $this->assertcount(1, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testXmlModPage(): void
    {
        $admin = $this->getUserByUsername('admin', isAdmin: true);

        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $magazineManager = $this->client->getContainer()->get(MagazineManager::class);
        $moderator = new ModeratorDto($this->getMagazineByName('acme'));
        $moderator->user = $this->getUserByUsername('Actor');
        $moderator->addedBy = $admin;
        $magazineManager->addModerator($moderator);

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/mod');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testFavPage(): void
    {
        $this->client = $this->prepareEntries();

        $favouriteManager = $this->favouriteManager;
        $favouriteManager->toggle(
            $this->getUserByUsername('Actor'),
            $this->getEntryByTitle('test entry 1', 'https://kbin.pub')
        );

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->request('GET', '/fav');
        $this->assertSelectorTextContains('h1', 'Hot');

        $crawler = $this->client->request('GET', '/fav/threads/newest');

        $this->assertSelectorTextContains('.entry__meta', 'JaneDoe');
        $this->assertSelectorTextContains('.entry__meta', 'to kbin');

        $this->assertSelectorTextContains('.head-title', '/fav');

        $this->assertSelectorTextContains('#header .active', 'Threads');

        $this->assertcount(1, $crawler->filter('.entry'));

        foreach ($this->getSortOptions() as $sortOption) {
            $crawler = $this->client->click($crawler->filter('.options__filter')->selectLink($sortOption)->link());
            $this->assertSelectorTextContains('.options__filter', $sortOption);
            $this->assertSelectorTextContains('h1', ucfirst($sortOption));
        }
    }

    public function testXmlFavPage(): void
    {
        $this->getEntryByTitle('test entry 1', 'https://kbin.pub');

        $favouriteManager = $this->favouriteManager;
        $favouriteManager->toggle(
            $this->getUserByUsername('Actor'),
            $this->getEntryByTitle('test entry 1', 'https://kbin.pub')
        );

        $this->client->loginUser($this->getUserByUsername('Actor'));

        $this->client->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');
        $this->client->request('GET', '/fav');

        $this->assertStringContainsString('{"html":', $this->client->getResponse()->getContent());
    }

    public function testCustomDefaultSort(): void
    {
        $older = $this->getEntryByTitle('Older entry');
        $older->createdAt = new \DateTimeImmutable('now - 1 day');
        $older->updateRanking();
        $this->entityManager->flush();
        $newer = $this->getEntryByTitle('Newer entry');
        $comment = $this->createEntryComment('someone was here', entry: $older);
        self::assertGreaterThan($older->getRanking(), $newer->getRanking());

        $user = $this->getUserByUsername('user');
        $user->frontDefaultSort = ESortOptions::Newest->value;
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.options__filter button', $this->translator->trans(ESortOptions::Newest->value));

        $iterator = $crawler->filter('#content div')->children()->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("entry-{$newer->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("entry-{$older->getId()}", $secondId);

        $user->frontDefaultSort = ESortOptions::Commented->value;
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.options__filter button', $this->translator->trans(ESortOptions::Commented->value));

        $iterator = $crawler->filter('#content div')->children()->getIterator();
        /** @var \DOMElement $firstNode */
        $firstNode = $iterator->current();
        $firstId = $firstNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("entry-{$older->getId()}", $firstId);
        $iterator->next();
        $secondNode = $iterator->current();
        $secondId = $secondNode->attributes->getNamedItem('id')->nodeValue;
        self::assertEquals("entry-{$newer->getId()}", $secondId);
    }

    private function prepareEntries(): KernelBrowser
    {
        $this->getEntryByTitle(
            'test entry 1',
            'https://kbin.pub',
            null,
            $this->getMagazineByName('kbin', $this->getUserByUsername('JaneDoe')),
            $this->getUserByUsername('JaneDoe')
        );

        // this  is necessary so the second entry is guaranteed to be newer than the first
        sleep(1);
        $this->getEntryByTitle('test entry 2', 'https://kbin.pub');

        return $this->client;
    }

    private function getSortOptions(): array
    {
        return ['Top', 'Hot', 'Newest', 'Active', 'Commented'];
    }

    private function clearTokens(string $responseContent): string
    {
        return preg_replace(
            '#name="token" value=".+"#',
            '',
            json_decode($responseContent, true, 512, JSON_THROW_ON_ERROR),
        )['html'];
    }

    private function clearDateTimes(string $responseContent): string
    {
        return preg_replace(
            '/<time ?[ \w=\"\'\-:+\n]*>[ \w\n]*<\/time>/m',
            '',
            $responseContent
        );
    }
}

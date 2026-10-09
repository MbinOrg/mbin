<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\Controller\Dev\M3u8EmbedFixtureController;
use App\DataFixtures\M3u8EmbedFixtures;
use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\User;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\UserRepository;
use App\Utils\M3u8EmbedFixture;
use App\Utils\Slugger;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

class M3u8EmbedFixturesTest extends TestCase
{
    public function testLoadingTwiceCreatesOnlyOneEntryAuthorAndMagazine(): void
    {
        $entries = $this->createMock(EntryRepository::class);
        $magazines = $this->createMock(MagazineRepository::class);
        $users = $this->createMock(UserRepository::class);
        $manager = $this->createMock(ObjectManager::class);
        $endpoint = new M3u8EmbedFixture('mbin.localhost:8123');
        $entry = null;
        $persisted = [];

        $entries->expects(self::exactly(2))->method('findOneBy')
            ->with(['url' => $endpoint->getMasterUrl()])
            ->willReturnCallback(function () use (&$entry): ?Entry { return $entry; });
        $users->expects(self::once())->method('findOneByUsername')->willReturn(null);
        $magazines->expects(self::once())->method('findOneByName')->willReturn(null);
        $manager->expects(self::exactly(3))->method('persist')
            ->willReturnCallback(function (object $entity) use (&$entry, &$persisted): void {
                $persisted[] = $entity;
                if ($entity instanceof Entry) {
                    $entry = $entity;
                }
            });
        $manager->expects(self::once())->method('flush');

        $fixture = new M3u8EmbedFixtures($entries, $magazines, $users, $endpoint, 'dev', new Slugger());
        $fixture->load($manager);
        $fixture->load($manager);

        self::assertInstanceOf(User::class, $persisted[0]);
        self::assertInstanceOf(Magazine::class, $persisted[1]);
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame($endpoint->getMasterUrl(), $entry->url);
        self::assertTrue($entry->hasEmbed);
        self::assertTrue($entry->sticky);
        self::assertSame(1, $entry->magazine->entryCount);
    }

    public function testConfiguredPortIsUsedByIframe(): void
    {
        $fixture = new M3u8EmbedFixture('mbin.localhost:8123');
        self::assertSame('http://mbin.localhost:8123/_dev/m3u8-embed/master.m3u8', $fixture->getMasterUrl());
        self::assertStringContainsString('src="'.$fixture->getMasterUrl().'"', $fixture->getIframeHtml());
    }

    public function testMasterPlaylist(): void
    {
        $response = (new M3u8EmbedFixtureController())->masterPlaylist();
        $masterPlaylist = (string) $response->getContent();

        self::assertSame('application/vnd.apple.mpegurl', $response->headers->get('Content-Type'));
        self::assertStringStartsWith("#EXTM3U\n", $masterPlaylist);
        self::assertStringContainsString('#EXT-X-STREAM-INF:', $masterPlaylist);
        self::assertStringEndsWith("variant.m3u8\n", $masterPlaylist);
    }
}

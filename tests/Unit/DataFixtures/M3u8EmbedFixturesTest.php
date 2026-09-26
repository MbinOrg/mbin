<?php

declare(strict_types=1);

namespace App\Tests\Unit\DataFixtures;

use App\DataFixtures\M3u8EmbedFixtures;
use PHPUnit\Framework\TestCase;

class M3u8EmbedFixturesTest extends TestCase
{
    private const string ASSET_DIR = __DIR__.'/../../assets/m3u8-embed';
    private const string MASTER_URL = 'http://127.0.0.1:8081/master.m3u8';

    public function testArticleAdvertisesFixtureOEmbedEndpoint(): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadHTMLFile(self::ASSET_DIR.'/article.html'));

        $links = $document->getElementsByTagName('link');
        self::assertCount(1, $links);
        self::assertSame('alternate', $links->item(0)->getAttribute('rel'));
        self::assertSame('application/json+oembed', $links->item(0)->getAttribute('type'));
        self::assertSame('http://127.0.0.1:8081/oembed.json', $links->item(0)->getAttribute('href'));
    }

    public function testOEmbedIframePointsToMasterPlaylist(): void
    {
        $oembed = json_decode(
            (string) file_get_contents(self::ASSET_DIR.'/oembed.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('video', $oembed['type']);
        self::assertSame(
            '<iframe src="'.self::MASTER_URL.'" width="640" height="360" allowfullscreen></iframe>',
            $oembed['html'],
        );
    }

    public function testFixtureEntryAndPlaylistUseSameEndpoint(): void
    {
        self::assertSame('http://127.0.0.1:8081/article.html', M3u8EmbedFixtures::ENTRY_URL);
        $masterPlaylist = (string) file_get_contents(self::ASSET_DIR.'/master.m3u8');

        self::assertStringStartsWith("#EXTM3U\n", $masterPlaylist);
        self::assertStringContainsString('#EXT-X-STREAM-INF:', $masterPlaylist);
        self::assertStringEndsWith("variant.m3u8\n", $masterPlaylist);
    }
}

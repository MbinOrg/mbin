<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\VideoManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoManagerTest extends TestCase
{
    #[DataProvider('streamManifestUrlProvider')]
    public function testStreamManifestUrlIsDetected(string $url): void
    {
        self::assertTrue(VideoManager::isStreamManifestUrl($url));
    }

    /**
     * @return \Generator<string, array{string}, mixed, void>
     */
    public static function streamManifestUrlProvider(): iterable
    {
        yield 'HLS playlist' => ['https://example.com/video/master.m3u8'];
        yield 'legacy HLS playlist' => ['https://example.com/video/playlist.m3u'];
        yield 'DASH manifest' => ['https://example.com/video/manifest.mpd'];
        yield 'Smooth Streaming manifest file' => ['https://example.com/video/channel.ism'];
        yield 'Smooth Streaming live manifest file' => ['https://example.com/video/channel.isml'];
        yield 'case-insensitive extension with query and fragment' => [
            'https://example.com/video/MASTER.M3U8?token=abc#fragment',
        ];
        yield 'Smooth Streaming manifest path' => ['https://example.com/video/channel.ism/Manifest'];
        yield 'Smooth Streaming manifest path with format' => [
            'https://example.com/video/channel.isml/Manifest(format=m3u8-aapl)',
        ];
    }

    #[DataProvider('nonStreamManifestUrlProvider')]
    public function testNonStreamManifestUrlIsNotDetected(string $url): void
    {
        self::assertFalse(VideoManager::isStreamManifestUrl($url));
    }

    /**
     * @return \Generator<string, array{string}, mixed, void>
     */
    public static function nonStreamManifestUrlProvider(): iterable
    {
        yield 'YouTube embed' => ['https://www.youtube.com/embed/abc123'];
        yield 'ordinary webpage' => ['https://example.com/watch/video'];
        yield 'extension text in query' => ['https://example.com/player?source=master.m3u8'];
        yield 'similar extension' => ['https://example.com/video/file.m3u80'];
        yield 'data URI' => ['data:application/vnd.apple.mpegurl;base64,AAAA'];
    }
}

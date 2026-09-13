<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Service\SettingsManager;
use App\Utils\Embed;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;

class EmbedTest extends TestCase
{
    public function testUnsupportedVideoSourceHtmlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<div><video controls><source src="https://example.com/master.m3u8" type="application/vnd.apple.mpegurl"></video></div>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testSupportedVideoSourceHtmlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<div><video controls><source src="https://example.com/video.mp4" type="video/mp4"></video></div>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testDataUriVideoSourceHtmlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<div><video controls src="data:video/mp4;base64,AAAA"></video></div>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testUnsupportedVideoContentWithOtherMarkupIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<div><p>Preview content</p><video controls><source src="https://example.com/master.m3u8" type="application/vnd.apple.mpegurl"></video></div>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testUnsupportedIframeStreamUrlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<iframe src="https://prod.vodvideo.cbsnews.com/cbsnews/vr/hls/2024/01/31/2305225795952/2643805_hls/master.m3u8" allowfullscreen></iframe>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testIframeVideoUrlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<iframe src="https://example.com/video.mp4" allowfullscreen></iframe>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testSupportedIframeEmbedUrlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');

        $html = '<iframe src="https://www.youtube.com/embed/abc123" allowfullscreen></iframe>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testCleaningEmbedRestoresLibxmlErrorHandling(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(Embed::class, 'cleanIframe');
        $previousSetting = libxml_use_internal_errors(false);

        try {
            $method->invoke($embed, '<iframe src="https://www.youtube.com/embed/abc123"></iframe>');

            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function createEmbed(): Embed
    {
        return new Embed(
            $this->createStub(CacheInterface::class),
            $this->createStub(SettingsManager::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
        );
    }
}

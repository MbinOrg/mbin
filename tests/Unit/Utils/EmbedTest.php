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

    private function createEmbed(): Embed
    {
        return new Embed(
            $this->createMock(CacheInterface::class),
            $this->createMock(SettingsManager::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );
    }
}

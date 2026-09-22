<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Service\SettingsManager;
use App\Utils\Embed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbedTest extends TestCase
{
    public function testPublicUrlIsRequested(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('<title>Public page</title>');
        });

        $result = $this->createEmbed($httpClient)->fetch('http://93.184.216.34/article');

        self::assertSame(1, $requestCount);
        self::assertSame('Public page', $result->title);
    }

    #[DataProvider('privateNetworkUrlProvider')]
    public function testPrivateNetworkUrlIsNotRequested(string $url): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('<title>Internal service</title>');
        });

        $result = $this->createEmbed($httpClient)->fetch($url);

        self::assertSame(0, $requestCount);
        self::assertNull($result->title);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateNetworkUrlProvider(): iterable
    {
        yield 'IPv4 loopback' => ['http://127.0.0.1/admin'];
        yield 'IPv4 private network' => ['http://192.168.1.1/admin'];
        yield 'IPv4 link-local network' => ['http://169.254.169.254/latest/meta-data'];
        yield 'IPv6 loopback' => ['http://[::1]/admin'];
        yield 'DNS-resolved loopback' => ['http://localhost/admin'];
    }

    public function testRedirectToPrivateNetworkIsNotRequested(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('', [
                'http_code' => 302,
                'redirect_url' => 'http://127.0.0.1/admin',
                'response_headers' => ['location: http://127.0.0.1/admin'],
            ]);
        });

        $result = $this->createEmbed($httpClient)->fetch('http://93.184.216.34/redirect');

        self::assertSame(1, $requestCount);
        self::assertNull($result->title);
    }

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

    private function createEmbed(?HttpClientInterface $httpClient = null): Embed
    {
        return new Embed(
            new ArrayAdapter(),
            $this->createStub(SettingsManager::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}

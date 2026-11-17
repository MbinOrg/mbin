<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Factory\WwwHttpClientFactory;
use App\Service\EmbedService;
use App\Service\SettingsManager;
use App\Service\UrlAccessChecker;
use App\Utils\M3u8EmbedFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmbedTest extends TestCase
{
    public function testOnlyHttpsIsRequested(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('<title>Public page</title>');
        });

        $this->createEmbed($httpClient)->fetch('http://93.184.216.34/article');
        $this->createEmbed($httpClient)->fetch('http://example.com/article');
        $this->createEmbed($httpClient)->fetch('http://example.com/image.jpg');
        $this->createEmbed($httpClient)->fetch('ftp://example.com/image.jpg');

        self::assertSame(0, $requestCount);
    }

    public function testUrlGetsHttpsPrepended(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function ($method, $url) use (&$requestedUrl): MockResponse {
            $requestedUrl = $url;

            return new MockResponse('<title>Public page</title>');
        });

        $this->createEmbed($httpClient)->fetch('example.com/article');
        self::assertSame('https://example.com/article', $requestedUrl);
    }

    public function testPublicUrlIsRequested(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('<title>Public page</title>');
        });

        $result = $this->createEmbed($httpClient)->fetch('https://93.184.216.34/article');

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

    public function testDevelopmentFixtureExercisesSanitizerWithoutNetworkRequest(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse();
        });

        $url = (new M3u8EmbedFixture('mbin.localhost:8080'))->getMasterUrl();
        $result = $this->createEmbed($httpClient, 'dev')->fetch($url);

        self::assertSame($url, $result->url);
        self::assertNull($result->html);
        self::assertSame(0, $requestCount);
    }

    #[DataProvider('fixtureIsolationProvider')]
    public function testFixtureShortcutIsRestricted(string $environment, string $url): void
    {
        $requests = 0;
        $httpClient = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse();
        });

        $result = $this->createEmbed($httpClient, $environment)->fetch($url);

        self::assertNull($result->title);
        self::assertSame(0, $requests);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fixtureIsolationProvider(): iterable
    {
        $url = (new M3u8EmbedFixture('mbin.localhost:8080'))->getMasterUrl();
        yield 'production' => ['prod', $url];
        yield 'test' => ['test', $url];
        yield 'different port' => ['dev', str_replace(':8080', ':8123', $url)];
        yield 'different path' => ['dev', $url.'?other=1'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateNetworkUrlProvider(): iterable
    {
        yield 'IPv4 loopback' => ['https://127.0.0.1/admin'];
        yield 'IPv4 private network' => ['https://192.168.1.1/admin'];
        yield 'IPv4 link-local network' => ['https://169.254.169.254/latest/meta-data'];
        yield 'IPv6 loopback' => ['https://[::1]/admin'];
        yield 'DNS-resolved loopback' => ['https://localhost/admin'];
        yield 'DNS-resolved loopback subdomain' => ['https://sub.localhost/admin'];
    }

    public function testRedirectToPrivateNetworkIsNotRequested(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('', [
                'http_code' => 302,
                'redirect_url' => 'https://127.0.0.1/admin',
                'response_headers' => ['location: https://127.0.0.1/admin'],
            ]);
        });

        $result = $this->createEmbed($httpClient)->fetch('https://93.184.216.34/redirect');

        self::assertSame(1, $requestCount);
        self::assertNull($result->title);
    }

    public function testFetchIsSkippedForImages(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('_');
        });

        $result = $this->createEmbed($httpClient)->fetch('https://example.com/image.jpg');

        self::assertSame(0, $requestCount);
        self::assertStringContainsString('<img src="https://example.com/image.jpg"', $result->html);
    }

    public function testFetchIsSkippedForVideos(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse('_');
        });

        $result = $this->createEmbed($httpClient)->fetch('https://example.com/video.webm');

        self::assertSame(0, $requestCount);
        self::assertStringContainsString('<video src="https://example.com/video.webm"', $result->html);
    }

    public function testUnsupportedVideoSourceHtmlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<div><video controls><source src="https://example.com/master.m3u8" type="application/vnd.apple.mpegurl"></video></div>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testSupportedVideoSourceHtmlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<div><video controls><source src="https://example.com/video.mp4" type="video/mp4"></video></div>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testDataUriVideoSourceHtmlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<div><video controls src="data:video/mp4;base64,AAAA"></video></div>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testUnsupportedVideoContentWithOtherMarkupIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<div><p>Preview content</p><video controls><source src="https://example.com/master.m3u8" type="application/vnd.apple.mpegurl"></video></div>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testUnsupportedIframeStreamUrlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<iframe src="https://prod.vodvideo.cbsnews.com/cbsnews/vr/hls/2024/01/31/2305225795952/2643805_hls/master.m3u8" allowfullscreen></iframe>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testIframeVideoUrlIsRejected(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<iframe src="https://example.com/video.mp4" allowfullscreen></iframe>';

        self::assertNull($method->invoke($embed, $html));
    }

    public function testSupportedIframeEmbedUrlIsKept(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');

        $html = '<iframe src="https://www.youtube.com/embed/abc123" allowfullscreen></iframe>';

        self::assertSame($html, $method->invoke($embed, $html));
    }

    public function testCleaningEmbedRestoresLibxmlErrorHandling(): void
    {
        $embed = $this->createEmbed();
        $method = new \ReflectionMethod(EmbedService::class, 'cleanIframe');
        $previousSetting = libxml_use_internal_errors(false);

        try {
            $method->invoke($embed, '<iframe src="https://www.youtube.com/embed/abc123"></iframe>');

            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousSetting);
        }
    }

    private function createEmbed(?HttpClientInterface $httpClient = null, string $kernelEnvironment = 'test'): EmbedService
    {
        return new EmbedService(
            new ArrayAdapter(),
            $this->createStub(SettingsManager::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            new WwwHttpClientFactory($httpClient ?? $this->createStub(HttpClientInterface::class)),
            new UrlAccessChecker(),
            $this->createStub(TranslatorInterface::class),
            $kernelEnvironment,
            new M3u8EmbedFixture('mbin.localhost:8080'),
        );
    }
}

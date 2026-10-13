<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\SettingsDto;
use App\Service\RegistrationScreening;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RegistrationScreeningTest extends TestCase
{
    private function service(MockHttpClient $client, int $limit = 90000): RegistrationScreening
    {
        return new RegistrationScreening($client, new ArrayAdapter(), new RateLimiterFactory([
            'id' => 'sfs', 'policy' => 'sliding_window', 'limit' => $limit, 'interval' => '24 hours',
        ], new InMemoryStorage()), new NullLogger(), 'test-secret');
    }

    public function testLookupUsesPostAndCachesResult(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.stopforumspam.org/api', $url);
            self::assertSame('ip=91.186.18.61&json=', $options['body']);
            self::assertSame(3.0, $options['max_duration']);
            self::assertSame(0, $options['max_redirects']);

            return new MockResponse('{"success":1,"ip":{"value":"91.186.18.61","appears":0,"frequency":0}}');
        });
        $service = $this->service($client);
        self::assertFalse($service->check('91.186.18.61')['cached']);
        $result = $service->check('91.186.18.61');
        self::assertTrue($result['cached']);
        self::assertSame('no_match', $result['status']);
        self::assertNull($result['confidence']);
        self::assertSame(1, $client->getRequestsCount());
    }

    #[DataProvider('thresholds')]
    public function testBothThresholdsAreRequired(?float $confidence, int $frequency, bool $expected): void
    {
        $settings = (new \ReflectionClass(SettingsDto::class))->newInstanceWithoutConstructor();
        $settings->MBIN_STOPFORUMSPAM_ENABLED = true;
        $settings->MBIN_STOPFORUMSPAM_AUTO_REJECT = true;
        $settings->MBIN_STOPFORUMSPAM_MIN_CONFIDENCE = 95;
        $settings->MBIN_STOPFORUMSPAM_MIN_FREQUENCY = 5;
        $service = $this->service(new MockHttpClient());
        $result = ['status' => 'matched', 'confidence' => $confidence, 'frequency' => $frequency];
        self::assertSame($expected, $service->shouldReject($result, $settings));
        $settings->MBIN_STOPFORUMSPAM_AUTO_REJECT = false;
        self::assertFalse($service->shouldReject($result, $settings));
        $settings->MBIN_STOPFORUMSPAM_AUTO_REJECT = true;
        $settings->MBIN_STOPFORUMSPAM_ENABLED = false;
        self::assertFalse($service->shouldReject($result, $settings));
    }

    public static function thresholds(): iterable
    {
        yield [95.0, 5, true];
        yield [94.99, 5, false];
        yield [99.9, 4, false];
        yield [null, 255, false];
        yield [99.95, 255, true];
    }

    #[DataProvider('failures')]
    public function testFailuresRemainUnavailable(string $body, int $code): void
    {
        $client = new MockHttpClient(new MockResponse($body, ['http_code' => $code]));
        $service = $this->service($client);
        self::assertSame('unavailable', $service->check('91.186.18.61')['status']);
        self::assertSame('unavailable', $service->check('91.186.18.61')['status']);
        self::assertSame(1, $client->getRequestsCount());
    }

    public static function failures(): iterable
    {
        yield ['{"success":0,"error":"request not understood"}', 200];
        yield ['not json', 200];
        yield ['', 503];
        yield ['', 302];
        yield ['{"success":1,"ip":{"value":"1.2.3.4","appears":1,"frequency":5,"confidence":99}}', 200];
        yield ['{"success":1,"ip":{"value":"91.186.18.61","appears":1,"frequency":5,"confidence":101}}', 200];
    }

    public function testInvalidIpDoesNotSendRequest(): void
    {
        $client = new MockHttpClient();
        $service = $this->service($client);
        self::assertSame('invalid_ip', $service->check(null)['failure']);
        self::assertSame('invalid_ip', $service->check('spoofed')['failure']);
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testQuotaExhaustionDoesNotSendAnotherRequest(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":1,"ip":{"value":"91.186.18.61","appears":0,"frequency":0}}'));
        $service = $this->service($client, 1);
        self::assertSame('no_match', $service->check('91.186.18.61')['status']);
        self::assertSame('quota_exhausted', $service->check('1.2.3.4')['failure']);
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testTimeoutIsUnavailable(): void
    {
        $body = (function (): \Generator {
            yield '';
        })();
        $service = $this->service(new MockHttpClient(new MockResponse($body)));
        self::assertSame('request_failed', $service->check('91.186.18.61')['failure']);
    }

    public function testThresholdChangesApplyToCachedResults(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":1,"ip":{"value":"91.186.18.61","appears":1,"frequency":5,"confidence":95}}'));
        $service = $this->service($client);
        $settings = (new \ReflectionClass(SettingsDto::class))->newInstanceWithoutConstructor();
        $settings->MBIN_STOPFORUMSPAM_ENABLED = true;
        $settings->MBIN_STOPFORUMSPAM_AUTO_REJECT = true;
        $settings->MBIN_STOPFORUMSPAM_MIN_CONFIDENCE = 96;
        $settings->MBIN_STOPFORUMSPAM_MIN_FREQUENCY = 5;
        self::assertFalse($service->shouldReject($service->check('91.186.18.61'), $settings));
        $settings->MBIN_STOPFORUMSPAM_MIN_CONFIDENCE = 95;
        self::assertTrue($service->shouldReject($service->check('91.186.18.61'), $settings));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testIpv6IsCanonicalized(): void
    {
        $client = new MockHttpClient(new MockResponse('{"success":1,"ip":{"value":"2001:db8::1","appears":1,"frequency":255,"confidence":99.95}}'));
        $service = $this->service($client);
        self::assertSame('2001:db8::1', $service->check('2001:0db8:0:0:0:0:0:1')['ip']);
        self::assertTrue($service->check('2001:db8::1')['cached']);
        self::assertSame(1, $client->getRequestsCount());
    }
}

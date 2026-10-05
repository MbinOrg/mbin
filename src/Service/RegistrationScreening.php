<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SettingsDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-type ScreeningResult array{ip: ?string, status: 'matched'|'no_match'|'unavailable', frequency: ?int, confidence: int|float|null, lastseen: ?string, checked_at: string, cached: bool, failure: ?string}
 */
class RegistrationScreening
{
    public function __construct(
        #[Autowire(service: 'stopforumspam.client')]
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly RateLimiterFactoryInterface $stopForumSpamLookupLimiter,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /** @return ScreeningResult */
    public function check(?string $ip): array
    {
        if (null === $ip || false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->unavailable(null, 'invalid_ip');
        }
        $ip = inet_ntop(inet_pton($ip));
        $fetched = false;
        try {
            $result = $this->cache->get('registration_sfs_'.hash_hmac('sha256', $ip, $this->secret), function (ItemInterface $item) use ($ip, &$fetched): array {
                $fetched = true;
                $result = $this->lookup($ip);
                $item->expiresAfter('unavailable' === $result['status'] ? 60 : 900);

                return $result;
            });
            $result['cached'] = !$fetched;

            return $result;
        } catch (\Exception) {
            return $this->unavailable($ip, 'cache_error');
        }
    }

    /** @param array{status: string, confidence: int|float|null, frequency: ?int} $result */
    public function shouldReject(array $result, SettingsDto $settings): bool
    {
        return true === $settings->MBIN_STOPFORUMSPAM_ENABLED
            && true === $settings->MBIN_STOPFORUMSPAM_AUTO_REJECT
            && 'matched' === $result['status']
            && null !== $result['confidence']
            && $result['confidence'] >= ($settings->MBIN_STOPFORUMSPAM_MIN_CONFIDENCE ?? 95)
            && $result['frequency'] >= ($settings->MBIN_STOPFORUMSPAM_MIN_FREQUENCY ?? 5);
    }

    /** @return ScreeningResult */
    private function lookup(string $ip): array
    {
        try {
            if (!$this->stopForumSpamLookupLimiter->create('stopforumspam')->consume()->isAccepted()) {
                return $this->unavailable($ip, 'quota_exhausted');
            }
            $response = $this->httpClient->request('POST', 'https://api.stopforumspam.org/api', [
                'body' => ['ip' => $ip, 'json' => ''],
                'timeout' => 3,
                'max_duration' => 3,
                'max_redirects' => 0,
            ]);
            if (200 !== $response->getStatusCode()) {
                $response->cancel();

                return $this->unavailable($ip, 'http_error');
            }
            $data = $response->toArray();
            $entry = $data['ip'] ?? null;
            if (1 !== ($data['success'] ?? null) || !\is_array($entry)
                || !\is_string($entry['value'] ?? null)
                || false === filter_var($entry['value'], FILTER_VALIDATE_IP)
                || inet_pton($entry['value']) !== inet_pton($ip)
                || !\in_array($entry['appears'] ?? null, [0, 1], true)
                || !\is_int($entry['frequency'] ?? null) || $entry['frequency'] < 0) {
                return $this->unavailable($ip, 'invalid_response');
            }
            $confidence = $entry['confidence'] ?? null;
            if (null !== $confidence && ((!\is_int($confidence) && !\is_float($confidence)) || !is_finite((float) $confidence) || $confidence < 0 || $confidence > 100)) {
                return $this->unavailable($ip, 'invalid_response');
            }

            return [
                'ip' => $ip,
                'status' => 1 === $entry['appears'] ? 'matched' : 'no_match',
                'frequency' => $entry['frequency'],
                'confidence' => $confidence,
                'lastseen' => \is_string($entry['lastseen'] ?? null) ? $entry['lastseen'] : null,
                'checked_at' => gmdate('c'),
                'cached' => false,
                'failure' => null,
            ];
        } catch (\Exception) {
            return $this->unavailable($ip, 'request_failed');
        }
    }

    /** @return ScreeningResult */
    private function unavailable(?string $ip, string $reason): array
    {
        $this->logger->warning('Registration screening unavailable: {reason}', ['reason' => $reason]);

        return ['ip' => $ip, 'status' => 'unavailable', 'frequency' => null, 'confidence' => null,
            'lastseen' => null, 'checked_at' => gmdate('c'), 'cached' => false, 'failure' => $reason];
    }
}

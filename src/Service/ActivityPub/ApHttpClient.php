<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Exception\InvalidWebfingerException;
use App\Factory\ActivityPub\GroupFactory;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\TombstoneFactory;
use App\Repository\MagazineRepository;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;
use App\Service\ProjectInfoService;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/*
 * source:
 * https://github.com/aaronpk/Nautilus/blob/master/app/ActivityPub/HTTPSignature.php
 * https://github.com/pixelfed/pixelfed/blob/dev/app/Util/ActivityPub/HttpSignature.php
 */

enum ApRequestType
{
    case ActivityPub;
    case WebFinger;
}

class ApHttpClient
{
    public const TIMEOUT = 8;

    public function __construct(
        private readonly string $kbinDomain,
        private readonly TombstoneFactory $tombstoneFactory,
        private readonly PersonFactory $personFactory,
        private readonly GroupFactory $groupFactory,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly UserRepository $userRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly SiteRepository $siteRepository,
        private readonly ProjectInfoService $projectInfo
    ) {
    }

    public function getActivityObject(string $url, bool $decoded = true): array|string|null
    {
        $resp = $this->cache->get('ap_'.hash('sha256', $url), function (ItemInterface $item) use ($url) {
            $this->logger->debug("ApHttpClient:getActivityObject:url: $url");

            $client = new CurlHttpClient();
            $r = $client->request('GET', $url, [
                'max_duration' => self::TIMEOUT,
                'timeout' => self::TIMEOUT,
                'headers' => $this->getInstanceHeaders($url),
            ]);

            $statusCode = $r->getStatusCode();
            // Accepted status code are 2xx or 410 (used Tombstone types)
            if (!str_starts_with((string) $statusCode, '2') && 410 !== $statusCode) {
                throw new InvalidApPostException("Invalid status code while getting: $url : $statusCode, ".substr($r->getContent(false), 0, 1000));
            }

            $item->expiresAt(new \DateTime('+1 hour'));

            // Read also non-OK responses (like 410) by passing 'false'
            return $r->getContent(false);
        });

        if (!$resp) {
            return null;
        }

        return $decoded ? json_decode($resp, true) : $resp;
    }

    /**
     * Retrieve AP actor object (could be a user or magazine).
     *
     * @return string return the inbox URL of the actor
     *
     * @throws \LogicException|InvalidApPostException if the AP actor object cannot be found
     */
    public function getInboxUrl(string $apProfileId): string
    {
        $actor = $this->getActorObject($apProfileId);
        if (!empty($actor)) {
            return $actor['endpoints']['sharedInbox'] ?? $actor['inbox'];
        } else {
            throw new \LogicException("Unable to find AP actor (user or magazine) with URL: $apProfileId");
        }
    }

    /**
     * Execute a webfinger request according to RFC 7033 (https://tools.ietf.org/html/rfc7033).
     *
     * @param string $url the URL of the user/magazine to get the webfinger object for
     *
     * @return array|null the webfinger object
     *
     * @throws InvalidWebfingerException|\Psr\Cache\InvalidArgumentException
     */
    public function getWebfingerObject(string $url): ?array
    {
        $resp = $this->cache->get(
            'wf_'.hash('sha256', $url),
            function (ItemInterface $item) use ($url) {
                $this->logger->debug("ApHttpClient:getWebfingerObject:url: $url");
                $r = null;
                try {
                    $client = new CurlHttpClient();
                    $r = $client->request('GET', $url, [
                        'max_duration' => self::TIMEOUT,
                        'timeout' => self::TIMEOUT,
                        'headers' => $this->getInstanceHeaders($url, null, 'get', ApRequestType::WebFinger),
                    ]);
                } catch (\Exception $e) {
                    $msg = "WebFinger Get fail: $url, ex: ".\get_class($e).": {$e->getMessage()}";
                    if (null !== $r) {
                        $msg .= ', '.$r->getContent(false);
                    }
                    throw new InvalidWebfingerException($msg);
                }

                $item->expiresAt(new \DateTime('+1 hour'));

                return $r->getContent();
            }
        );

        return $resp ? json_decode($resp, true) : null;
    }

    /**
     * Retrieve AP actor object (could be a user or magazine).
     *
     * @return array|null key/value array of actor response body
     *
     * @throws InvalidApPostException|\Psr\Cache\InvalidArgumentException
     */
    public function getActorObject(string $apProfileId): ?array
    {
        $resp = $this->cache->get(
            'ap_'.hash('sha256', $apProfileId),
            function (ItemInterface $item) use ($apProfileId) {
                $this->logger->debug("ApHttpClient:getActorObject:url: $apProfileId");
                $response = null;
                try {
                    // Set-up request
                    $client = new CurlHttpClient();
                    $response = $client->request('GET', $apProfileId, [
                        'max_duration' => self::TIMEOUT,
                        'timeout' => self::TIMEOUT,
                        'headers' => $this->getInstanceHeaders($apProfileId, null, 'get', ApRequestType::ActivityPub),
                    ]);
                    // If 4xx error response, try to find the actor locally
                    if (str_starts_with((string) $response->getStatusCode(), '4')) {
                        if ($user = $this->userRepository->findOneByApProfileId($apProfileId)) {
                            $user->apDeletedAt = new \DateTime();
                            $this->userRepository->save($user, true);
                        }
                        if ($magazine = $this->magazineRepository->findOneByApProfileId($apProfileId)) {
                            $magazine->apDeletedAt = new \DateTime();
                            $this->magazineRepository->save($magazine, true);
                        }
                    }
                } catch (\Exception $e) {
                    // If an exception occurred, try to find the actor locally
                    if ($user = $this->userRepository->findOneByApProfileId($apProfileId)) {
                        $user->apTimeoutAt = new \DateTime();
                        $this->userRepository->save($user, true);
                    }
                    if ($magazine = $this->magazineRepository->findOneByApProfileId($apProfileId)) {
                        $magazine->apTimeoutAt = new \DateTime();
                        $this->magazineRepository->save($magazine, true);
                    }

                    $msg = "AP Get fail: $apProfileId, ex: ".\get_class($e).": {$e->getMessage()}";
                    if (null !== $response) {
                        $msg .= ', '.$response->getContent(false);
                    }
                    throw new InvalidApPostException($msg);
                }

                $item->expiresAt(new \DateTime('+1 hour'));

                if (404 === $response->getStatusCode()) {
                    // treat a 404 error the same as a tombstone, since we think there was an actor, but it isn't there anymore
                    return $this->tombstoneFactory->create($apProfileId);
                }

                // Return the content.
                // Pass the 'false' option to getContent so it doesn't throw errors on "non-OK" respones (eg. 410 status codes).
                return $response->getContent(false);
            }
        );

        return $resp ? json_decode($resp, true) : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getCollectionObject(string $apAddress)
    {
        $resp = $this->cache->get(
            'ap_collection'.hash('sha256', $apAddress),
            function (ItemInterface $item) use ($apAddress) {
                $this->logger->debug("ApHttpClient:getCollectionObject:url: $apAddress");
                $response = null;
                try {
                    // Set-up request
                    $client = new CurlHttpClient();
                    $response = $client->request('GET', $apAddress, [
                        'max_duration' => self::TIMEOUT,
                        'timeout' => self::TIMEOUT,
                        'headers' => $this->getInstanceHeaders($apAddress, null, 'get', ApRequestType::ActivityPub),
                    ]);

                    $statusCode = $response->getStatusCode();
                    // Accepted status code are 2xx or 410 (used Tombstone types)
                    if (!str_starts_with((string) $statusCode, '2') && 410 !== $statusCode) {
                        throw new InvalidApPostException("Invalid status code while getting: $apAddress : $statusCode, ".substr($response->getContent(false), 0, 1000));
                    }
                } catch (\Exception $e) {
                    $msg = "AP Get fail: $apAddress, ex: ".\get_class($e).": {$e->getMessage()}";
                    if (null !== $response) {
                        $msg .= ', '.$response->getContent(false);
                    }
                    throw new InvalidApPostException($msg);
                }

                $item->expiresAt(new \DateTime('+24 hour'));

                // When everything goes OK, return the data
                return $response->getContent();
            }
        );

        return $resp ? json_decode($resp, true) : null;
    }

    /**
     * Sends a POST request to the specified URL with optional request body and caching mechanism.
     *
     * @param string        $url   the URL to which the POST request will be sent
     * @param User|Magazine $actor The actor initiating the request, either a User or Magazine object
     * @param array|null    $body  (Optional) The body of the POST request. Defaults to null.
     *
     * @throws InvalidApPostException if the POST request fails with a non-2xx response status code
     */
    public function post(string $url, User|Magazine $actor, array $body = null): void
    {
        $cacheKey = 'ap_'.hash('sha256', $url.':'.$body['id']);

        if ($this->cache->hasItem($cacheKey)) {
            return;
        }

        $this->logger->debug("ApHttpClient:post:url: $url");
        $this->logger->debug('ApHttpClient:post:body '.json_encode($body ?? []));

        // Set-up request
        $client = new CurlHttpClient();
        $response = $client->request('POST', $url, [
            'max_duration' => self::TIMEOUT,
            'timeout' => self::TIMEOUT,
            'body' => json_encode($body),
            'headers' => $this->getHeaders($url, $actor, $body),
        ]);

        if (!str_starts_with((string) $response->getStatusCode(), '2')) {
            throw new InvalidApPostException("Post fail: $url, ".substr($response->getContent(false), 0, 1000).' '.json_encode($body));
        }

        // build cache
        $item = $this->cache->getItem($cacheKey);
        $item->set(true);
        $item->expiresAt(new \DateTime('+45 minutes'));
        $this->cache->save($item);
    }

    private static function headersToCurlArray($headers): array
    {
        return array_map(function ($k, $v) {
            return "$k: $v";
        }, array_keys($headers), $headers);
    }

    private function getHeaders(string $url, User|Magazine $actor, array $body = null): array
    {
        $headers = self::headersToSign($url, $body ? self::digest($body) : null);
        $stringToSign = self::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
        $key = openssl_pkey_get_private($actor->privateKey);
        openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        $keyId = $actor instanceof User
            ? $this->personFactory->getActivityPubId($actor).'#main-key'
            : $this->groupFactory->getActivityPubId($actor).'#main-key';

        $signatureHeader = 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
        unset($headers['(request-target)']);
        $headers['Signature'] = $signatureHeader;
        $headers['User-Agent'] = $this->projectInfo->getUserAgent().'/'.$this->projectInfo->getVersion().' (+https://'.$this->kbinDomain.'/agent)';
        $headers['Accept'] = 'application/activity+json, application/ld+json';
        $headers['Content-Type'] = 'application/activity+json';

        return $headers;
    }

    private function getInstanceHeaders(string $url, array $body = null, string $method = 'get', ApRequestType $requestType = ApRequestType::ActivityPub): array
    {
        $keyId = 'https://'.$this->kbinDomain.'/i/actor#main-key';
        $privateKey = $this->getInstancePrivateKey();
        $headers = self::headersToSign($url, $body ? self::digest($body) : null, $method);
        $stringToSign = self::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
        $key = openssl_pkey_get_private($privateKey);
        openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);
        $signatureHeader = 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
        unset($headers['(request-target)']);
        $headers['Signature'] = $signatureHeader;
        $headers['User-Agent'] = $this->projectInfo->getUserAgent().'/'.$this->projectInfo->getVersion().' (+https://'.$this->kbinDomain.'/agent)';
        if (ApRequestType::WebFinger === $requestType) {
            $headers['Accept'] = 'application/jrd+json';
            $headers['Content-Type'] = 'application/jrd+json';
        } else {
            $headers['Accept'] = 'application/activity+json, application/ld+json';
            $headers['Content-Type'] = 'application/activity+json';
        }

        return $headers;
    }

    #[ArrayShape([
        '(request-target)' => 'string',
        'Date' => 'string',
        'Host' => 'mixed',
        'Accept' => 'string',
        'Digest' => 'string',
    ])]
    protected static function headersToSign(string $url, string $digest = null, string $method = 'post'): array
    {
        $date = new \DateTime('UTC');

        if (!\in_array($method, ['post', 'get'])) {
            throw new InvalidApPostException('Invalid method used to sign headers in ApHttpClient');
        }
        $headers = [
            '(request-target)' => $method.' '.parse_url($url, PHP_URL_PATH),
            'Date' => $date->format('D, d M Y H:i:s \G\M\T'),
            'Host' => parse_url($url, PHP_URL_HOST),
        ];

        if (!empty($digest)) {
            $headers['Digest'] = 'SHA-256='.$digest;
        }

        return $headers;
    }

    private static function digest(array $body): string
    {
        return base64_encode(hash('sha256', json_encode($body), true));
    }

    private static function headersToSigningString(array $headers): string
    {
        return implode(
            "\n",
            array_map(function ($k, $v) {
                return strtolower($k).': '.$v;
            }, array_keys($headers), $headers)
        );
    }

    private function getInstancePrivateKey(): string
    {
        return $this->cache->get('instance_private_key', function (ItemInterface $item) {
            $item->expiresAt(new \DateTime('+1 day'));

            return $this->siteRepository->findAll()[0]->privateKey;
        });
    }

    public function getInstancePublicKey(): string
    {
        return $this->cache->get('instance_public_key', function (ItemInterface $item) {
            $item->expiresAt(new \DateTime('+1 day'));

            return $this->siteRepository->findAll()[0]->publicKey;
        });
    }
}

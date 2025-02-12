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
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/*
 * source:
 * https://github.com/aaronpk/Nautilus/blob/master/app/ActivityPub/HTTPSignature.php
 * https://github.com/pixelfed/pixelfed/blob/dev/app/Util/ActivityPub/HttpSignature.php
 */

enum ApRequestType
{
    case ActivityPub;
    case WebFinger;
    case NodeInfo;
}

class ApHttpClient implements ApHttpClientInterface
{
    public const TIMEOUT = 8;
    public const MAX_DURATION = 15;

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
        private readonly ProjectInfoService $projectInfo,
    ) {
    }

    /**
     * Retrieve a remote activity object from an URL. And cache the result.
     *
     * @param bool $decoded (optional)
     *
     * @return array|string|null JSON Response body (as PHP Object)
     */
    public function getActivityObject(string $url, bool $decoded = true): array|string|null
    {
        $key = $this->getActivityObjectCacheKey($url);
        if ($this->cache->hasItem($key)) {
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $resp = $item->get();

            return $decoded ? json_decode($resp, true) : $resp;
        }

        $resp = $this->getActivityObjectImpl($url);

        if (!$resp) {
            return null;
        }

        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->expiresAt(new \DateTime('+1 hour'));
        $item->set($resp);
        $this->cache->save($item);

        return $decoded ? json_decode($resp, true) : $resp;
    }

    /**
     * Do a GET request for an ActivityPub object and return the response content.
     *
     * @return string|null returns the response content or null if the request failed
     *
     * @throws InvalidApPostException
     */
    private function getActivityObjectImpl(string $url): ?string
    {
        $this->logger->debug("[ApHttpClient::getActivityObjectImpl] URL: $url");
        $content = null;
        try {
            $client = new CurlHttpClient();
            $response = $client->request('GET', $url, [
                'max_duration' => self::MAX_DURATION,
                'timeout' => self::TIMEOUT,
                'headers' => $this->getInstanceHeaders($url),
            ]);

            $statusCode = $response->getStatusCode();
            // Accepted status code are 2xx or 410 (used Tombstone types)
            if (!str_starts_with((string) $statusCode, '2') && 410 !== $statusCode) {
                // Do NOT include the response content in the error message, this will be often a full HTML page
                throw new InvalidApPostException('Invalid status code while getting', $url, $statusCode);
            }

            // Read also non-OK responses (like 410) by passing 'false'
            $content = $response->getContent(false);
            $this->logger->debug('[ApHttpClient::getActivityObjectImpl] URL: {url} - content: {content}', ['url' => $url, 'content' => $content]);
        } catch (\Exception $e) {
            $this->logRequestException($response, $url, 'ApHttpClient:getActivityObject', $e);
        }

        return $content;
    }

    public function getActivityObjectCacheKey(string $url): string
    {
        return 'ap_object_'.hash('sha256', $url);
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
     * @return array|null The webfinger object (as PHP Object)
     *
     * @throws InvalidWebfingerException|InvalidArgumentException
     */
    public function getWebfingerObject(string $url): ?array
    {
        $key = 'wf_'.hash('sha256', $url);
        if ($this->cache->hasItem($key)) {
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $resp = $item->get();

            return $resp ? json_decode($resp, true) : null;
        }

        $resp = $this->getWebfingerObjectImpl($url);

        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->expiresAt(new \DateTime('+1 hour'));
        $item->set($resp);
        $this->cache->save($item);

        return $resp ? json_decode($resp, true) : null;
    }

    private function getWebfingerObjectImpl(string $url): ?string
    {
        $this->logger->debug("[ApHttpClient::getWebfingerObjectImpl] URL: $url");
        $response = null;
        try {
            $client = new CurlHttpClient();
            $response = $client->request('GET', $url, [
                'max_duration' => self::MAX_DURATION,
                'timeout' => self::TIMEOUT,
                'headers' => $this->getInstanceHeaders($url, null, 'get', ApRequestType::WebFinger),
            ]);
        } catch (\Exception $e) {
            $this->logRequestException($response, $url, 'ApHttpClient:getWebfingerObject', $e);
        }

        return $response->getContent();
    }

    private function getActorCacheKey(string $apProfileId): string
    {
        return 'ap_'.hash('sha256', $apProfileId);
    }

    private function getCollectionCacheKey(string $apAddress): string
    {
        return 'ap_collection'.hash('sha256', $apAddress);
    }

    /**
     * Retrieve AP actor object (could be a user or magazine).
     *
     * @return array|null key/value array of actor response body (as PHP Object)
     *
     * @throws InvalidApPostException|InvalidArgumentException
     */
    public function getActorObject(string $apProfileId): ?array
    {
        $key = $this->getActorCacheKey($apProfileId);
        if ($this->cache->hasItem($key)) {
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $resp = $item->get();

            return $resp ? json_decode($resp, true) : null;
        }

        $resp = $this->getActorObjectImpl($apProfileId);

        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->expiresAt(new \DateTime('+1 hour'));
        $item->set($resp);
        $this->cache->save($item);

        return $resp ? json_decode($resp, true) : null;
    }

    private function getActorObjectImpl(string $apProfileId): ?string
    {
        $this->logger->debug("[ApHttpClient::getActorObjectImpl] URL: $apProfileId");
        $response = null;
        try {
            // Set-up request
            $client = new CurlHttpClient();
            $response = $client->request('GET', $apProfileId, [
                'max_duration' => self::MAX_DURATION,
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
        } catch (\Exception|TransportExceptionInterface $e) {
            // If an exception occurred, try to find the actor locally
            if ($user = $this->userRepository->findOneByApProfileId($apProfileId)) {
                $user->apTimeoutAt = new \DateTime();
                $this->userRepository->save($user, true);
            }
            if ($magazine = $this->magazineRepository->findOneByApProfileId($apProfileId)) {
                $magazine->apTimeoutAt = new \DateTime();
                $this->magazineRepository->save($magazine, true);
            }
            $this->logRequestException($response, $apProfileId, 'ApHttpClient:getActorObject', $e);
        }

        if (404 === $response->getStatusCode()) {
            // treat a 404 error the same as a tombstone, since we think there was an actor, but it isn't there anymore
            return json_encode($this->tombstoneFactory->create($apProfileId));
        }

        // Return the content.
        // Pass the 'false' option to getContent so it doesn't throw errors on "non-OK" respones (eg. 410 status codes).
        return $response->getContent(false);
    }

    /**
     * Remove actor object from cache.
     *
     * @param string $apProfileId AP profile ID to remove from cache
     */
    public function invalidateActorObjectCache(string $apProfileId): void
    {
        $this->cache->delete($this->getActorCacheKey($apProfileId));
    }

    /**
     * Remove collection object from cache.
     *
     * @param string $apAddress AP address to remove from cache
     */
    public function invalidateCollectionObjectCache(string $apAddress): void
    {
        $this->cache->delete($this->getCollectionCacheKey($apAddress));
    }

    /**
     * Retrieve AP collection object. First look in cache, then try to retrieve from AP server.
     * And finally, save the response to cache.
     *
     * @return array|null JSON Response body (as PHP Object)
     *
     * @throws InvalidArgumentException
     */
    public function getCollectionObject(string $apAddress): ?array
    {
        $key = $this->getCollectionCacheKey($apAddress);
        if ($this->cache->hasItem($key)) {
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $resp = $item->get();

            return $resp ? json_decode($resp, true) : null;
        }

        $resp = $this->getCollectionObjectImpl($apAddress);

        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->expiresAt(new \DateTime('+24 hour'));
        $item->set($resp);
        $this->cache->save($item);

        return $resp ? json_decode($resp, true) : null;
    }

    private function getCollectionObjectImpl(string $apAddress): ?string
    {
        $this->logger->debug("[ApHttpClient::getCollectionObjectImpl] URL: $apAddress");
        $response = null;
        try {
            // Set-up request
            $client = new CurlHttpClient();
            $response = $client->request('GET', $apAddress, [
                'max_duration' => self::MAX_DURATION,
                'timeout' => self::TIMEOUT,
                'headers' => $this->getInstanceHeaders($apAddress, null, 'get', ApRequestType::ActivityPub),
            ]);

            $statusCode = $response->getStatusCode();
            // Accepted status code are 2xx or 410 (used Tombstone types)
            if (!str_starts_with((string) $statusCode, '2') && 410 !== $statusCode) {
                // Do NOT include the response content in the error message, this will be often a full HTML page
                throw new InvalidApPostException('Invalid status code while getting', $apAddress, $statusCode);
            }
        } catch (\Exception $e) {
            $this->logRequestException($response, $apAddress, 'ApHttpClient:getCollectionObject', $e);
        }

        // When everything goes OK, return the data
        return $response->getContent();
    }

    /**
     * Helper function for logging get/post/.. requests to the error & debug log with additional info.
     *
     * @param ResponseInterface|null $response    Optional response object
     * @param string                 $requestUrl  Full URL of the request
     * @param string                 $requestType an additional string where the error happened in the code
     * @param \Exception             $e           Error object
     *
     * @throws InvalidApPostException rethrows the error
     */
    private function logRequestException(?ResponseInterface $response, string $requestUrl, string $requestType, \Exception $e): void
    {
        if (null !== $response) {
            try {
                $content = $response->getContent(false);
            } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                $class = \get_class($e);
                $content = "there was an exception while getting the content, $class: {$e->getMessage()}";
            }
        }

        // Often 400, 404 errors just return the full HTML page, so we don't want to log the full content of them
        // We truncate the content to 200 characters max.
        $this->logger->error('[ApHttpClient::logRequestException] {type} failed: {address}, ex: {e}: {msg}. Truncated content: {content}', [
            'type' => $requestType,
            'address' => $requestUrl,
            'e' => \get_class($e),
            'msg' => $e->getMessage(),
            'content' => substr($content ?? 'No content provided', 0, 200),
        ]);
        // And only log the full content in debug log mode
        if ($content) {
            $this->logger->debug('[ApHttpClient::logRequestException] Full response body content: {content}', [
                'content' => $content,
            ]);
        }
        throw $e; // re-throw the exception
    }

    /**
     * Sends a POST request to the specified URL with optional request body and caching mechanism.
     *
     * @param string        $url   the URL to which the POST request will be sent
     * @param User|Magazine $actor The actor initiating the request, either a User or Magazine object
     * @param array|null    $body  (Optional) The body of the POST request. Defaults to null.
     *
     * @throws InvalidApPostException      if the POST request fails with a non-2xx response status code
     * @throws TransportExceptionInterface
     */
    public function post(string $url, User|Magazine $actor, ?array $body = null): void
    {
        $cacheKey = 'ap_'.hash('sha256', $url.':'.$body['id']);

        if ($this->cache->hasItem($cacheKey)) {
            $this->logger->warning('[ApHttpClient::post] Not posting activity with id {id} to {inbox} again, as we already did that sometime in the last 45 minutes', [
                'id' => $body['id'],
                'inbox' => $url,
            ]);

            return;
        }

        $jsonBody = json_encode($body ?? []);

        $this->logger->debug("[ApHttpClient::post] URL: $url");
        $this->logger->debug("[ApHttpClient::post] Body: $jsonBody");

        // Set-up request
        try {
            $client = new CurlHttpClient();
            $response = $client->request('POST', $url, [
                'max_duration' => self::MAX_DURATION,
                'timeout' => self::TIMEOUT,
                'body' => $jsonBody,
                'headers' => $this->getHeaders($url, $actor, $body),
            ]);

            $statusCode = $response->getStatusCode();
            if (!str_starts_with((string) $statusCode, '2')) {
                // Do NOT include the response content in the error message, this will be often a full HTML page
                throw new InvalidApPostException('Post failed', $url, $statusCode, $body);
            }
        } catch (\Exception $e) {
            $this->logRequestException($response, $url, 'ApHttpClient:post', $e);
        }

        // build cache
        $item = $this->cache->getItem($cacheKey);
        $item->set(true);
        $item->expiresAt(new \DateTime('+45 minutes'));
        $this->cache->save($item);
    }

    public function fetchInstanceNodeInfoEndpoints(string $domain, bool $decoded = true): array|string|null
    {
        $url = "https://$domain/.well-known/nodeinfo";

        $resp = $this->generalFetchCached('nodeinfo_endpoints_', 'nodeinfo endpoints', $url, ApRequestType::NodeInfo);

        if (!$resp) {
            return null;
        }

        return $decoded ? json_decode($resp, true) : $resp;
    }

    public function fetchInstanceNodeInfo(string $url, bool $decoded = true): array|string|null
    {
        $resp = $this->generalFetchCached('nodeinfo_', 'nodeinfo', $url, ApRequestType::NodeInfo);

        if (!$resp) {
            return null;
        }

        return $decoded ? json_decode($resp, true) : $resp;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function generalFetch(string $url, ApRequestType $requestType = ApRequestType::ActivityPub): string
    {
        $client = new CurlHttpClient();
        $this->logger->debug("[ApHttpClient::generalFetch] URL: $url");
        $r = $client->request('GET', $url, [
            'max_duration' => self::MAX_DURATION,
            'timeout' => self::TIMEOUT,
            'headers' => $this->getInstanceHeaders($url, requestType: $requestType),
        ]);

        return $r->getContent();
    }

    private function generalFetchCached(string $cachePrefix, string $fetchType, string $url, ApRequestType $requestType = ApRequestType::ActivityPub): ?string
    {
        $key = $cachePrefix.hash('sha256', $url);

        if ($this->cache->hasItem($key)) {
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);

            return $item->get();
        }

        try {
            $resp = $this->generalFetch($url, $requestType);
        } catch (\Exception $e) {
            $this->logger->warning('[ApHttpClient::generalFetchCached] There was an exception fetching {type} from {url}: {e} - {msg}', [
                'type' => $fetchType,
                'url' => $url,
                'e' => \get_class($e),
                'msg' => $e->getMessage(),
            ]);
            $resp = null;
        }

        if (!$resp) {
            return null;
        }

        $item = $this->cache->getItem($key);
        $item->set($resp);
        $item->expiresAt(new \DateTime('+1 day'));
        $this->cache->save($item);

        return $resp;
    }

    private function getFetchAcceptHeaders(ApRequestType $requestType): array
    {
        return match ($requestType) {
            ApRequestType::WebFinger => [
                'Accept' => 'application/jrd+json',
                'Content-Type' => 'application/jrd+json',
            ],
            ApRequestType::ActivityPub => [
                'Accept' => 'application/activity+json',
                'Content-Type' => 'application/activity+json',
            ],
            ApRequestType::NodeInfo => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        };
    }

    private static function headersToCurlArray($headers): array
    {
        return array_map(function ($k, $v) {
            return "$k: $v";
        }, array_keys($headers), $headers);
    }

    private function getHeaders(string $url, User|Magazine $actor, ?array $body = null): array
    {
        $headers = self::headersToSign($url, $body ? self::digest($body) : null);
        $stringToSign = self::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
        $key = openssl_pkey_get_private($actor->privateKey);
        $success_sign = openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        // Free the key from memory
        openssl_free_key($key);
        $signatureHeader = null;
        if ($success_sign) {
            $signature = base64_encode($signature);
            $keyId = $actor instanceof User
                ? $this->personFactory->getActivityPubId($actor).'#main-key'
                : $this->groupFactory->getActivityPubId($actor).'#main-key';
            $signatureHeader = 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
        } else {
            $this->logger->error('[ApHttpClient::getHeaders] Failed to sign headers for {url}: {headers}', [
                'url' => $url,
                'headers' => $headers,
            ]);
            throw new \Exception('Failed to sign headers');
        }
        unset($headers['(request-target)']);
        if ($signatureHeader) {
            $headers['Signature'] = $signatureHeader;
        }
        $headers['User-Agent'] = $this->projectInfo->getUserAgent();
        $headers['Accept'] = 'application/activity+json';
        $headers['Content-Type'] = 'application/activity+json';

        return $headers;
    }

    private function getInstanceHeaders(string $url, ?array $body = null, string $method = 'get', ApRequestType $requestType = ApRequestType::ActivityPub): array
    {
        $keyId = 'https://'.$this->kbinDomain.'/i/actor#main-key';
        $privateKey = $this->getInstancePrivateKey();
        $headers = self::headersToSign($url, $body ? self::digest($body) : null, $method);
        $stringToSign = self::headersToSigningString($headers);
        $signedHeaders = implode(' ', array_map('strtolower', array_keys($headers)));
        $key = openssl_pkey_get_private($privateKey);
        $success_sign = openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        // Free the key from memory
        openssl_free_key($key);
        $signatureHeader = null;
        if ($success_sign) {
            $signature = base64_encode($signature);
            $signatureHeader = 'keyId="'.$keyId.'",headers="'.$signedHeaders.'",algorithm="rsa-sha256",signature="'.$signature.'"';
        } else {
            $this->logger->error('[ApHttpClient::getInstanceHeaders] Failed to sign headers for {url}: {headers}', [
                'url' => $url,
                'headers' => $headers,
            ]);
            throw new \Exception('Failed to sign headers');
        }
        unset($headers['(request-target)']);
        if ($signatureHeader) {
            $headers['Signature'] = $signatureHeader;
        }
        $headers['User-Agent'] = $this->projectInfo->getUserAgent();
        $headers = array_merge($headers, $this->getFetchAcceptHeaders($requestType));

        return $headers;
    }

    #[ArrayShape([
        '(request-target)' => 'string',
        'Date' => 'string',
        'Host' => 'mixed',
        'Accept' => 'string',
        'Digest' => 'string',
    ])]
    protected static function headersToSign(string $url, ?string $digest = null, string $method = 'post'): array
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

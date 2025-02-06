<?php

declare(strict_types=1);

namespace App\Service\ActivityPub;

use App\Entity\Magazine;
use App\Entity\User;
use App\Exception\InvalidApPostException;
use App\Exception\InvalidWebfingerException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

interface ApHttpClientInterface
{
    /**
     * Retrieve a remote activity object from an URL. And cache the result.
     *
     * @param bool $decoded (optional)
     *
     * @return array|string|null JSON Response body (as PHP Object)
     */
    public function getActivityObject(string $url, bool $decoded = true): array|string|null;

    public function getActivityObjectCacheKey(string $url): string;

    /**
     * Retrieve AP actor object (could be a user or magazine).
     *
     * @return string return the inbox URL of the actor
     *
     * @throws \LogicException|InvalidApPostException if the AP actor object cannot be found
     */
    public function getInboxUrl(string $apProfileId): string;

    /**
     * Execute a webfinger request according to RFC 7033 (https://tools.ietf.org/html/rfc7033).
     *
     * @param string $url the URL of the user/magazine to get the webfinger object for
     *
     * @return array|null The webfinger object (as PHP Object)
     *
     * @throws InvalidWebfingerException|InvalidArgumentException
     */
    public function getWebfingerObject(string $url): ?array;

    /**
     * Retrieve AP actor object (could be a user or magazine).
     *
     * @return array|null key/value array of actor response body (as PHP Object)
     *
     * @throws InvalidApPostException|InvalidArgumentException
     */
    public function getActorObject(string $apProfileId): ?array;

    /**
     * Remove actor object from cache.
     *
     * @param string $apProfileId AP profile ID to remove from cache
     */
    public function invalidateActorObjectCache(string $apProfileId): void;

    /**
     * Remove collection object from cache.
     *
     * @param string $apAddress AP address to remove from cache
     */
    public function invalidateCollectionObjectCache(string $apAddress): void;

    /**
     * Retrieve AP collection object. First look in cache, then try to retrieve from AP server.
     * And finally, save the response to cache.
     *
     * @return array|null JSON Response body (as PHP Object)
     *
     * @throws InvalidArgumentException
     */
    public function getCollectionObject(string $apAddress): ?array;

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
    public function post(string $url, User|Magazine $actor, ?array $body = null): void;

    public function fetchInstanceNodeInfoEndpoints(string $domain, bool $decoded = true): array|string|null;

    public function fetchInstanceNodeInfo(string $url, bool $decoded = true): array|string|null;

    public function getInstancePublicKey(): string;
}

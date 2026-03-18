<?php

namespace App\Tests\Service;

use App\Entity\Magazine;
use App\Entity\User;
use App\Service\ActivityPub\ApHttpClientInterface;

class ApHttpClientProxy implements ApHttpClientInterface
{

    public ?ApHttpClientInterface $replacement = null;

    public function __construct(
        private ApHttpClientInterface $defaultClient
    ){}

    private function client(): ApHttpClientInterface
    {
        return $this->replacement ?? $this->defaultClient;
    }

    public function getActivityObject(string $url, bool $decoded = true): array|string|null
    {
        return $this->client()->getActivityObject($url, $decoded);
    }

    public function getActivityObjectCacheKey(string $url): string
    {
        return $this->client()->getActivityObjectCacheKey($url);
    }

    public function getInboxUrl(string $apProfileId): string
    {
        return $this->client()->getInboxUrl($apProfileId);
    }

    public function getWebfingerObject(string $url): ?array
    {
        return $this->client()->getWebfingerObject($url);
    }

    public function getActorObject(string $apProfileId): ?array
    {
        return $this->client()->getActorObject($apProfileId);
    }

    public function invalidateActorObjectCache(string $apProfileId): void
    {
        $this->client()->invalidateActorObjectCache($apProfileId);
    }

    public function invalidateCollectionObjectCache(string $apAddress): void
    {
        $this->client()->invalidateCollectionObjectCache($apAddress);
    }

    public function getCollectionObject(string $apAddress): ?array
    {
        return $this->client()->getCollectionObject($apAddress);
    }

    public function post(string $url, Magazine|User $actor, ?array $body = null, bool $useOldPrivateKey = false): void
    {
        $this->client()->post($url, $actor, $body, $useOldPrivateKey);
    }

    public function fetchInstanceNodeInfoEndpoints(string $domain, bool $decoded = true): array|string|null
    {
        return $this->client()->fetchInstanceNodeInfoEndpoints($domain, $decoded);
    }

    public function fetchInstanceNodeInfo(string $url, bool $decoded = true): array|string|null
    {
        return $this->client()->fetchInstanceNodeInfo($url, $decoded);
    }

    public function getInstancePublicKey(): string
    {
        return $this->client()->getInstancePublicKey();
    }
}

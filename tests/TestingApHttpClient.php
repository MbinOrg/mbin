<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Magazine;
use App\Entity\User;
use App\Service\ActivityPub\ApHttpClientInterface;

class TestingApHttpClient implements ApHttpClientInterface
{
    /**
     * @phpstan-var array<string, array> $activityObjects
     */
    public array $activityObjects = [];

    /**
     * @phpstan-var array<string, array> $collectionObjects
     */
    public array $collectionObjects = [];

    /**
     * @phpstan-var array<string, array> $webfingerObjects
     */
    public array $webfingerObjects = [];

    /**
     * @phpstan-var array<string, array> $actorObjects
     */
    public array $actorObjects = [];

    /**
     * @var array<int, array{inboxUrl: string, payload: array, actor: User|Magazine}>
     */
    private array $postedObjects = [];

    public function getActivityObject(string $url, bool $decoded = true): array|string|null
    {
        if (\array_key_exists($url, $this->activityObjects)) {
            return $this->activityObjects[$url];
        }

        return null;
    }

    public function getCollectionObject(string $apAddress): ?array
    {
        if (\array_key_exists($apAddress, $this->collectionObjects)) {
            return $this->collectionObjects[$apAddress];
        }

        return null;
    }

    public function getActorObject(string $apProfileId): ?array
    {
        if (\array_key_exists($apProfileId, $this->actorObjects)) {
            return $this->actorObjects[$apProfileId];
        }

        return null;
    }

    public function getWebfingerObject(string $url): ?array
    {
        if (\array_key_exists($url, $this->webfingerObjects)) {
            return $this->webfingerObjects[$url];
        }

        return null;
    }

    public function fetchInstanceNodeInfoEndpoints(string $domain, bool $decoded = true): array|string|null
    {
        return null;
    }

    public function fetchInstanceNodeInfo(string $url, bool $decoded = true): array|string|null
    {
        return null;
    }

    public function post(string $url, Magazine|User $actor, ?array $body = null, bool $useOldPrivateKey = false): void
    {
        $this->postedObjects[] = [
            'inboxUrl' => $url,
            'actor' => $actor,
            'payload' => $body,
        ];
    }

    /**
     * @return array<int, array{inboxUrl: string, payload: array, actor: User|Magazine}>
     */
    public function getPostedObjects(): array
    {
        return $this->postedObjects;
    }

    public function getActivityObjectCacheKey(string $url): string
    {
        return 'SOME_TESTING_CACHE_KEY';
    }

    public function getInboxUrl(string $apProfileId): string
    {
        $actor = $this->getActorObject($apProfileId);
        if (!empty($actor)) {
            return $actor['endpoints']['sharedInbox'] ?? $actor['inbox'];
        } else {
            throw new \LogicException("Unable to find AP actor (user or magazine) with URL: $apProfileId");
        }
    }

    public function invalidateActorObjectCache(string $apProfileId): void
    {
    }

    public function invalidateCollectionObjectCache(string $apAddress): void
    {
    }

    public function getInstancePublicKey(): string
    {
        return 'TESTING PUBLIC KEY';
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Instance;
use OpenApi\Attributes as OA;

#[OA\Schema]
class RemoteInstanceDto implements \JsonSerializable
{
    public function __construct(
        public ?string $software = null,
        public ?string $version = null,
        public string $domain,
        public ?\DateTimeImmutable $lastSuccessfulDeliver = null,
        public ?\DateTimeImmutable $lastFailedDeliver = null,
        public ?\DateTimeImmutable $lastSuccessfulReceive = null,
        public int $failedDelivers = 0,
        public bool $isBanned = false,
        public bool $isExplicitlyAllowed = false,
        public int $id,
        public int $magazines,
        public int $users,
        #[OA\Property(description: 'Amount of users from our instance following users on their instance')]
        public int $ourUserFollows,
        #[OA\Property(description: 'Amount of users from their instance following users on our instance')]
        public int $theirUserFollows,
        #[OA\Property(description: 'Amount of users on our instance subscribed to magazines from their instance')]
        public int $ourSubscriptions,
        #[OA\Property(description: 'Amount of users from their instance subscribed to magazines on our instance')]
        public int $theirSubscriptions,
    ) {
    }

    /**
     * @param array{magazines: int, users: int, theirUserFollows: int, ourUserFollows: int, theirSubscriptions: int, ourSubscriptions: int} $instanceCounts
     */
    public static function create(Instance $instance, array $instanceCounts): RemoteInstanceDto
    {
        return new self(
            $instance->software,
            $instance->version,
            $instance->domain,
            $instance->getLastSuccessfulDeliver(),
            $instance->getLastFailedDeliver(),
            $instance->getLastSuccessfulReceive(),
            $instance->getFailedDelivers(),
            $instance->isBanned,
            $instance->isExplicitlyAllowed,
            $instance->getId(),
            $instanceCounts['magazines'],
            $instanceCounts['users'],
            $instanceCounts['ourUserFollows'],
            $instanceCounts['theirUserFollows'],
            $instanceCounts['ourSubscriptions'],
            $instanceCounts['theirSubscriptions'],
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'software' => $this->software,
            'version' => $this->version,
            'domain' => $this->domain,
            'lastSuccessfulDeliver' => $this->lastSuccessfulDeliver,
            'lastFailedDeliver' => $this->lastFailedDeliver,
            'lastSuccessfulReceive' => $this->lastSuccessfulReceive,
            'failedDelivers' => $this->failedDelivers,
            'isBanned' => $this->isBanned,
            'isExplicitlyAllowed' => $this->isExplicitlyAllowed,
            'id' => $this->id,
            'magazines' => $this->magazines,
            'users' => $this->users,
            'ourUserFollows' => $this->ourUserFollows,
            'theirUserFollows' => $this->theirUserFollows,
            'ourSubscriptions' => $this->ourSubscriptions,
            'theirSubscriptions' => $this->theirSubscriptions,
        ];
    }
}

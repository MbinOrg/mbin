<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class FederationSettingsDto implements \JsonSerializable
{
    public function __construct(
        public bool $federationEnabled,
        public bool $federationUsesAllowList,
        public bool $federationPageEnabled,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'federationEnabled' => $this->federationEnabled,
            'federationUsesAllowList' => $this->federationUsesAllowList,
            'federationPageEnabled' => $this->federationPageEnabled,
        ];
    }
}

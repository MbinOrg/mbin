<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class InstanceDto implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(description: 'the domain of the instance', example: 'instance.tld')]
        public string $domain,
        #[OA\Property(description: 'the software the instance is running on', example: 'mbin')]
        public ?string $software = null,
        #[OA\Property(description: 'the version of the software', example: '1.6.0')]
        public ?string $version = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'domain' => $this->domain,
            'software' => $this->software,
            'version' => $this->version,
        ];
    }
}

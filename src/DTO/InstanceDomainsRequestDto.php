<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['domains'])]
class InstanceDomainsRequestDto
{
    #[OA\Property(type: 'array', nullable: false, items: new OA\Items(type: 'string'), example: ['example.com'])]
    public array $domains = [];
}

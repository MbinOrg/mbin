<?php

declare(strict_types=1);

namespace App\DTO;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class InstancesDtoV2 implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: InstanceDto::class)))]
        public ?array $instances
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'instances' => $this->instances,
        ];
    }
}

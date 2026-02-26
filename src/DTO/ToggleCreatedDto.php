<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class ToggleCreatedDto implements \JsonSerializable
{
    #[OA\Property(description: 'if true the resource was created, if false it was deleted')]
    public bool $created;

    public function __construct(bool $created)
    {
        $this->created = $created;
    }

    public function jsonSerialize(): mixed
    {
        return ['created' => $this->created];
    }
}

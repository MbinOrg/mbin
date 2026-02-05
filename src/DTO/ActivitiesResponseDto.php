<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema]
class ActivitiesResponseDto implements \JsonSerializable
{

    #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: UserSmallResponseDto::class), description: 'null if the user is not allowed to access the data')]
    public ?array $boosts = null;
    #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: UserSmallResponseDto::class), description: 'null if the user is not allowed to access the data')]
    public ?array $upvotes = null;
    #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: UserSmallResponseDto::class), description: 'null if the user is not allowed to access the data')]
    public ?array $downvotes = null;

    /**
     * @param UserSmallResponseDto[]|null $boosts
     * @param UserSmallResponseDto[]|null $upvotes
     * @param UserSmallResponseDto[]|null $downvotes
     * @return ActivitiesResponseDto
     */
    public static function create(
        ?array $boosts = null,
        ?array $upvotes = null,
        ?array $downvotes = null,
    ): ActivitiesResponseDto {
        $dto = new self();
        $dto->boosts = $boosts;
        $dto->upvotes = $upvotes;
        $dto->downvotes = $downvotes;
        return $dto;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'boosts' => $this->boosts,
            'upvotes' => $this->upvotes,
            'downvotes' => $this->downvotes,
        ];
    }
}

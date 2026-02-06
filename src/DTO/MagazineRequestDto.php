<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class MagazineRequestDto
{
    public ?string $name = null;
    public ?string $title = null;
    public ?string $description = null;

    #[OA\Property(description: 'If this field is populated, it will throw a BadRequestException.', deprecated: true)]
    public ?string $rules = null;
    public ?bool $isAdult = null;
    public ?bool $isPostingRestrictedToMods = null;
    public ?bool $discoverable = null;
    public ?bool $indexable = null;

    public function mergeIntoDto(MagazineDto $dto): MagazineDto
    {
        $dto->name = $this->name ?? $dto->name;
        $dto->title = $this->title ?? $dto->title;
        $dto->description = $this->description ?? $dto->description;
        $dto->rules = $this->rules ?? $dto->rules;
        $dto->isAdult = null !== $this->isAdult ? $this->isAdult : $dto->isAdult;
        $dto->isPostingRestrictedToMods = $this->isPostingRestrictedToMods ?? false;
        $dto->discoverable = $this->discoverable ?? $dto->discoverable ?? true;
        $dto->indexable = $this->indexable ?? $dto->indexable ?? true;

        return $dto;
    }
}

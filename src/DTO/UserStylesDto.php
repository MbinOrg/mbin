<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class UserStylesDto implements \JsonSerializable
{

    public function __construct(
        public ?string $customCss = null,
        public ?string $customPublicContentCss = null,
        public ?string $customPublicProfileCss = null,
        public ?bool $ignoreMagazinesCustomCss = null,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'customCss' => $this->customCss,
            'customPublicContentCss' => $this->customPublicContentCss,
            'customPublicProfileCss' => $this->customPublicProfileCss,
            'ignoreMagazinesCustomCss' => $this->ignoreMagazinesCustomCss,
        ];
    }

    public function mergeIntoDto(UserStylesDto $dto): UserStylesDto
    {

        $dto->customCss = $this->customCss ?? $dto->customCss;
        $dto->customPublicContentCss = $this->customPublicContentCss ?? $dto->customPublicContentCss;
        $dto->customPublicProfileCss = $this->customPublicProfileCss ?? $dto->customPublicProfileCss;
        $dto->ignoreMagazinesCustomCss = $this->ignoreMagazinesCustomCss ?? $dto->ignoreMagazinesCustomCss;

        return $dto;
    }
}
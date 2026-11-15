<?php
declare(strict_types=1);

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
        public ?bool $ignoreUserContentCss = null,
        public ?bool $ignoreUserProfileCss = null,
    ) {
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'customCss' => $this->customCss,
            'customPublicContentCss' => $this->customPublicContentCss,
            'customPublicProfileCss' => $this->customPublicProfileCss,
            'ignoreMagazinesCustomCss' => $this->ignoreMagazinesCustomCss,
            'ignoreUserContentCss' => $this->ignoreUserContentCss,
            'ignoreUserProfileCss' => $this->ignoreUserProfileCss,
        ];
    }

    public function mergeIntoDto(UserStylesDto $dto): UserStylesDto
    {

        $dto->customCss = $this->customCss ?? $dto->customCss;
        $dto->customPublicContentCss = $this->customPublicContentCss ?? $dto->customPublicContentCss;
        $dto->customPublicProfileCss = $this->customPublicProfileCss ?? $dto->customPublicProfileCss;
        $dto->ignoreMagazinesCustomCss = $this->ignoreMagazinesCustomCss ?? $dto->ignoreMagazinesCustomCss;
        $dto->ignoreUserContentCss = $this->ignoreUserContentCss ?? $dto->ignoreUserContentCss;
        $dto->ignoreUserProfileCss = $this->ignoreUserProfileCss ?? $dto->ignoreUserProfileCss;

        return $dto;
    }
}

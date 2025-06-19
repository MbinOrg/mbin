<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\BookmarkList;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints\NotBlank;

#[OA\Schema()]
class BookmarkListDto implements \JsonSerializable
{
    #[NotBlank]
    #[OA\Property(description: "The id of the list")]
    public int $id;

    #[NotBlank]
    #[OA\Property(description: 'The name of the list')]
    public string $name;

    #[OA\Property(description: 'Whether this is the default list')]
    public bool $isDefault = false;

    #[OA\Property(description: 'The total number of items in the list')]
    public int $count = 0;

    public static function fromList(BookmarkList $list): BookmarkListDto
    {
        $dto = new BookmarkListDto();
        $dto->id = $list->getId();
        $dto->name = $list->name;
        $dto->isDefault = $list->isDefault;
        $dto->count = $list->entities->count();

        return $dto;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isDefault' => $this->isDefault,
            'count' => $this->count,
        ];
    }
}

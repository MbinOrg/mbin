<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\UserFilterList;
use OpenApi\Attributes as OA;

#[OA\Schema]
class UserFilterListResponseDto implements \JsonSerializable
{
    public ?int $id = null;
    public string $name;

    public ?string $expirationDate;

    public bool $feeds;
    public bool $comments;
    public bool $profile;

    /**
     * @var array<array{word: string, exactMatch: bool}>
     */
    public array $words = [];

    public static function fromList(UserFilterList $list): self
    {
        $dto = new self();
        $dto->id = $list->getId();
        $dto->name = $list->name;
        $dto->expirationDate = $list->expirationDate?->format(DATE_ATOM);
        $dto->feeds = $list->feeds;
        $dto->comments = $list->comments;
        $dto->profile = $list->profile;
        $dto->words = $list->words;

        return $dto;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'expirationDate' => $this->expirationDate,
            'feeds' => $this->feeds,
            'comments' => $this->comments,
            'profile' => $this->profile,
            'words' => $this->words,
        ];
    }
}

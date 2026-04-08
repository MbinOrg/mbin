<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\UserFilterList;

class UserFilterListDto
{
    public ?int $id = null;
    public string $name;

    public ?\DateTimeImmutable $expirationDate;

    public bool $feeds;
    public bool $comments;
    public bool $profile;

    /** @var UserFilterWordDto[] */
    public array $words = [];

    public function wordsToArray(): array
    {
        $nonEmptyWords = array_filter($this->words, fn (?UserFilterWordDto $word) => null !== $word?->word && '' !== trim($word->word));

        return array_map(fn (UserFilterWordDto $word) => [
            'word' => $word->word,
            'exactMatch' => $word->exactMatch,
        ], $nonEmptyWords);
    }

    public function addEmptyWords(): void
    {
        $wordsToAdd = 5 - \sizeof($this->words);
        if ($wordsToAdd <= 0) {
            $wordsToAdd = 1;
        }

        for ($i = 0; $i < $wordsToAdd; ++$i) {
            $this->words[] = new UserFilterWordDto();
        }
    }

    public static function fromList(UserFilterList $list): self
    {
        $dto = new self();
        $dto->id = $list->getId();
        $dto->name = $list->name;
        $dto->expirationDate = $list->expirationDate;
        $dto->feeds = $list->feeds;
        $dto->comments = $list->comments;
        $dto->profile = $list->profile;
        foreach ($list->words as $word) {
            $dto2 = new UserFilterWordDto();
            $dto2->word = $word['word'];
            $dto2->exactMatch = $word['exactMatch'];
            $dto->words[] = $dto2;
        }

        return $dto;
    }
}

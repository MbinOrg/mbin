<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class HashtagResponseDto implements \JsonSerializable
{
    public string $tag;
    public int $entryCount;
    public int $entryCommentCount;
    public int $postCount;
    public int $postCommentCount;
    public ?bool $isBlockedByUser = null;

    public static function create(
        string $tag,
        int $entryCount,
        int $entryCommentCount,
        int $postCount,
        int $postCommentCount,
    ): self
    {
        $toReturn = new HashtagResponseDto();
        $toReturn->tag = $tag;
        $toReturn->entryCount = $entryCount;
        $toReturn->entryCommentCount = $entryCommentCount;
        $toReturn->postCount = $postCount;
        $toReturn->postCommentCount = $postCommentCount;
        $toReturn->isBlockedByUser = null;

        return $toReturn;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'tag' => $this->tag,
            'entryCount' => $this->entryCount,
            'entryCommentCount' => $this->entryCommentCount,
            'postCount' => $this->postCount,
            'postCommentCount' => $this->postCommentCount,
            'isBlockedByUser' => $this->isBlockedByUser,
        ];
    }
}
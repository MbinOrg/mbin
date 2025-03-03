<?php

declare(strict_types=1);

namespace App\DTO;

use App\Service\SettingsManager;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class PostCommentRequestDto extends ContentRequestDto
{
    public function mergeIntoDto(PostCommentDto $dto, SettingsManager $settingsManager): PostCommentDto
    {
        $dto->image = $this->image ?? $dto->image;
        $dto->body = $this->body ?? $dto->body;
        $dto->lang = $this->lang ?? $dto->lang ?? $settingsManager->getValue('KBIN_DEFAULT_LANG');
        $dto->isAdult = $this->isAdult ?? $dto->isAdult;

        return $dto;
    }
}

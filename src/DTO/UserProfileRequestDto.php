<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\NoSurroundingWhitespace;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema()]
class UserProfileRequestDto
{
    #[Assert\Length(min: 2, max: UserDto::MAX_ABOUT_LENGTH, countUnit: Assert\Length::COUNT_GRAPHEMES)]
    public ?string $about = null;

    #[Assert\Length(min: 2, max: UserDto::MAX_USERNAME_LENGTH)]
    #[NoSurroundingWhitespace]
    public ?string $displayname = null;
}

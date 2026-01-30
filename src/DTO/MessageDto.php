<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class MessageDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 5000, countUnit: Assert\Length::COUNT_GRAPHEMES)]
    public ?string $body = null;

    public ?string $apId = null;
}

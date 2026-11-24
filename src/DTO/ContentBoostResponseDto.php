<?php

declare(strict_types=1);

namespace App\DTO;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema()]
class ContentBoostResponseDto implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(ref: new Model(type: UserSmallResponseDto::class))]
        public UserSmallResponseDto $user,
        public \DateTimeImmutable $boostedAt,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'user' => $this->user,
            'boostedAt' => $this->boostedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}

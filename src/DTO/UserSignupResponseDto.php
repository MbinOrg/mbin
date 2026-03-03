<?php

declare(strict_types=1);

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema()]
class UserSignupResponseDto implements \JsonSerializable
{
    public int $userId = 0;
    public string $username = '';
    public bool $isBot = false;
    public ?\DateTimeImmutable $createdAt = null;
    public ?string $email = null;
    public ?string $applicationText = null;

    public function __construct(UserDto $dto)
    {
        $this->userId = $dto->getId();
        $this->username = $dto->username;
        $this->isBot = $dto->isBot;
        $this->createdAt = $dto->createdAt;
        $this->email = $dto->email;
        $this->applicationText = $dto->applicationText;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'userId' => $this->userId,
            'username' => $this->username,
            'isBot' => $this->isBot,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'email' => $this->email,
            'applicationText' => $this->applicationText,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\PollChoice;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Core\User\UserInterface;

#[OA\Schema]
class PollChoiceResponseDto implements \JsonSerializable
{
    public string $name;

    public int $voteCount;

    public ?bool $currentUserHasVoted = null;

    public static function createFromPollChoice(PollChoice $choice, UserInterface|User|null $user): self
    {
        $dto = new self();
        $dto->name = $choice->name;
        $dto->voteCount = $choice->voteCount;
        $dto->currentUserHasVoted = $user instanceof User ? $choice->hasUserVoted($user) : null;

        return $dto;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'voteCount' => $this->voteCount,
            'currentUserHasVoted' => $this->currentUserHasVoted,
        ];
    }
}

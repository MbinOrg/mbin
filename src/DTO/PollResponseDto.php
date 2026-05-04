<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Poll;
use App\Entity\PollChoice;
use App\Entity\User;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Core\User\UserInterface;

class PollResponseDto implements \JsonSerializable
{
    public int $voterCount = 0;
    public ?bool $currentUserHasVoted = null;
    #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: PollChoiceResponseDto::class)))]
    public ?array $choices = null;

    public static function createFromPoll(Poll $poll, User|UserInterface|null $user): self
    {
        $dto = new PollResponseDto();
        $dto->voterCount = $poll->voterCount;
        $dto->currentUserHasVoted = $user instanceof User ? $poll->hasUserVoted($user) : null;
        $dto->choices = array_map(fn (PollChoice $choice) => PollChoiceResponseDto::createFromPollChoice($choice, $user), $poll->choices->toArray());

        return $dto;
    }

    public function jsonSerialize(): array
    {
        return [
            'voterCount' => $this->voterCount,
            'currentUserHasVoted' => $this->currentUserHasVoted,
            'choices' => $this->choices ? array_map(fn (PollChoiceResponseDto $dto) => $dto->jsonSerialize(), $this->choices) : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Contracts;

trait PollDtoTrait
{
    public ?bool $addPoll = null;
    public ?bool $isMultipleChoicePoll = null;
    public ?\DateTimeImmutable $pollEndsAt;
    /**
     * @var ?string[]
     */
    public ?array $choices = [];
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\DTO\Contracts\PollDtoTrait;

class ContentWithPollDto
{
    use PollDtoTrait;

    public function __construct()
    {
        $this->pollEndsAt = new \DateTimeImmutable('now + 7 days');
    }

    public function addEmptyChoices(): void
    {
        $choicesToAdd = 5 - \sizeof($this->choices);
        if ($choicesToAdd <= 0) {
            $choicesToAdd = 1;
        }

        for ($i = 0; $i < $choicesToAdd; ++$i) {
            $this->choices[] = '';
        }
    }
}

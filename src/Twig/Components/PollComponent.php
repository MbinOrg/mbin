<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Poll;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('poll')]
class PollComponent
{
    public Poll $poll;

    public bool $isExternal = false;
}

<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ConfirmDefederationDto
{
    #[Assert\NotNull]
    #[Assert\IsTrue]
    public bool $confirm;
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\User;
use App\Validator\Unique;
use Symfony\Component\Validator\Constraints as Assert;

#[Unique(Moderator::class, errorPath: 'user', fields: ['magazine', 'user'])]
class ModeratorDto
{
    public ?Magazine $magazine = null;
    #[Assert\NotBlank]
    public ?User $user = null;
    public ?User $addedBy = null;

    public function __construct(?Magazine $magazine, ?User $user = null, ?User $addedBy = null)
    {
        $this->magazine = $magazine;
        $this->user = $user;
        $this->addedBy = $addedBy;
    }
}

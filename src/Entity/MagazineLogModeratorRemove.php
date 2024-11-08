<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ContentInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MagazineLogModeratorRemove extends MagazineLog
{
    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $actingUser;

    public function __construct(Magazine $magazine, User $addedMod, ?User $actingUser)
    {
        parent::__construct($magazine, $addedMod);
        $this->actingUser = $actingUser;
    }

    public function getSubject(): ?ContentInterface
    {
        return null;
    }

    public function clearSubject(): MagazineLog
    {
        return $this;
    }

    public function getType(): string
    {
        return 'log_moderator_remove';
    }
}

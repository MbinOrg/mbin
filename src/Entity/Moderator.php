<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[Table]
#[UniqueConstraint(name: 'moderator_magazine_user_idx', columns: ['magazine_id', 'user_id'])]
class Moderator
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[ManyToOne(targetEntity: User::class, inversedBy: 'moderatorTokens')]
    #[JoinColumn(nullable: false)]
    public User $user;
    #[ManyToOne(targetEntity: Magazine::class, inversedBy: 'moderators')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Magazine $magazine;
    #[ManyToOne(targetEntity: User::class, inversedBy: 'moderatorTokens')]
    #[JoinColumn(nullable: true)]
    public ?User $addedByUser;
    #[Column(type: 'boolean', nullable: false)]
    public bool $isOwner = false;
    #[Column(type: 'boolean', nullable: false)]
    public bool $isConfirmed = false;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    public function __construct(Magazine $magazine, User $user, User $addedByUser = null, $isOwner = false, $isConfirmed = false)
    {
        $this->magazine = $magazine;
        $this->user = $user;
        $this->addedByUser = $addedByUser;
        $this->isOwner = $isOwner;
        $this->isConfirmed = $isConfirmed;

        $magazine->moderators->add($this);
        $user->moderatorTokens->add($this);

        $this->createdAtTraitConstruct();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function __sleep()
    {
        return [];
    }
}

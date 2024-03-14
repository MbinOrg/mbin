<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\FavouriteInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Repository\FavouriteRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity(repositoryClass: FavouriteRepository::class)]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorColumn(name: 'favourite_type', type: 'text')]
#[DiscriminatorMap([
    'entry' => 'EntryFavourite',
    'entry_comment' => 'EntryCommentFavourite',
    'post' => 'PostFavourite',
    'post_comment' => 'PostCommentFavourite',
])]
#[UniqueConstraint(name: 'favourite_user_entry_unique_idx', columns: ['entry_id', 'user_id'])]
#[UniqueConstraint(name: 'favourite_user_entry_comment_unique_idx', columns: ['entry_comment_id', 'user_id'])]
#[UniqueConstraint(name: 'favourite_user_post_unique_idx', columns: ['post_id', 'user_id'])]
#[UniqueConstraint(name: 'favourite_user_post_comment_unique_idx', columns: ['post_comment_id', 'user_id'])]
abstract class Favourite
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[ManyToOne(targetEntity: Magazine::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Magazine $magazine;
    #[ManyToOne(targetEntity: User::class, inversedBy: 'favourites')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->createdAtTraitConstruct();
    }

    public function getId(): int
    {
        return $this->id;
    }

    abstract public function getType(): string;

    abstract public function getSubject(): FavouriteInterface;

    abstract public function clearSubject(): Favourite;
}

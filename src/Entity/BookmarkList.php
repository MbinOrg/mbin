<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[UniqueConstraint(columns: ['user_id', 'name'])]
class BookmarkList
{
    #[Column, Id, GeneratedValue]
    private int $id;

    #[OneToMany(mappedBy: 'list', targetEntity: Bookmark::class, orphanRemoval: true)]
    #[JoinColumn(onDelete: 'CASCADE')]
    public Collection $entities;

    #[ManyToOne(inversedBy: 'bookmarkLists')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[Column(nullable: false)]
    public string $name;

    #[Column]
    public bool $isDefault = false;

    public function __construct(User $user, string $name, bool $isDefault = false)
    {
        $this->user = $user;
        $this->name = $name;
        $this->isDefault = $isDefault;
        $this->entities = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }
}

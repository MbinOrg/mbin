<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class UserFilterList
{
    use CreatedAtTrait;

    #[Column, Id, GeneratedValue]
    private int $id;

    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: User::class, inversedBy: 'filterLists')]
    public User $user;

    #[Column]
    public string $name;

    #[Column(nullable: true)]
    public ?\DateTimeImmutable $expirationDate;

    #[Column]
    public bool $feeds = false;

    #[Column]
    public bool $profile = false;

    #[Column]
    public bool $comments = false;

    /**
     * @var array<array{word: string, exactMatch: bool}> $words
     */
    #[Column(type: Types::JSONB)]
    public array $words = [];

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getLocationStrings(): array
    {
        $res = [];
        if ($this->feeds) {
            $res[] = 'feeds';
        }
        if ($this->profile) {
            $res[] = 'profile';
        }
        if ($this->comments) {
            $res[] = 'comments';
        }

        return $res;
    }
}

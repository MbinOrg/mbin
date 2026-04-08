<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Repository\ApActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity(repositoryClass: ApActivityRepository::class)]
class ApActivity
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;
    #[ManyToOne(targetEntity: Magazine::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Magazine $magazine;
    #[Column(type: 'string', nullable: false)]
    public int $subjectId;
    #[Column(type: 'string', nullable: false)]
    public string $type;
    #[Column(type: Types::JSONB, nullable: true)]
    public string $body;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;
}

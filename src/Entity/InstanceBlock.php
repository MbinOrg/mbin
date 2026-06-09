<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InstanceBlockRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity(repositoryClass: InstanceBlockRepository::class)]
#[UniqueConstraint(name: 'instance_block_idx', columns: ['user_id', 'instance_domain'])]
class InstanceBlock
{
    public function __construct(User $user, Instance $instance, bool $blockedByAdmin)
    {
        $this->user = $user;
        $this->instance = $instance;
        $this->instanceDomain = $instance->domain;
        $this->blockedByAdmin = $blockedByAdmin;
    }

    #[Column, Id, GeneratedValue]
    private int $id;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ManyToOne(targetEntity: Instance::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Instance $instance;

    // denormalized schema to avoid many JOINs
    #[Column]
    public string $instanceDomain;

    #[Column]
    public bool $blockedByAdmin;
}

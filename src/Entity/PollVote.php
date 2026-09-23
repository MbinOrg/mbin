<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Traits\ActivityPubActivityTrait;
use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\CustomIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Symfony\Component\Uid\Uuid;

#[Entity]
class PollVote implements ActivityPubActivityInterface
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }
    use ActivityPubActivityTrait;

    #[Column(type: 'uuid'), Id, GeneratedValue(strategy: 'CUSTOM')]
    #[CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public Uuid $uuid;

    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: User::class)]
    public User $voter;

    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: PollChoice::class)]
    public PollChoice $choice;

    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: Poll::class)]
    public Poll $poll;

    public function __construct()
    {
        $this->createdAtTraitConstruct();
    }

    public function getUser(): ?User
    {
        return $this->voter;
    }
}

<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping\Cache;
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
#[UniqueConstraint(name: 'hashtag_subscription_idx', columns: ['user_id', 'hashtag_id'])]
#[Cache(usage: 'NONSTRICT_READ_WRITE')]
class HashtagSubscription
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[ManyToOne(targetEntity: User::class, inversedBy: 'subscribedHashtags')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?User $user;
    #[ManyToOne(targetEntity: Hashtag::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Hashtag $hashtag;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    public function __construct(User $user, Hashtag $hashtag)
    {
        $this->createdAtTraitConstruct();

        $this->user = $user;
        $this->hashtag = $hashtag;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}

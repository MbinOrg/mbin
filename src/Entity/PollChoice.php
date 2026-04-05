<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity]
class PollChoice
{
    #[Id, GeneratedValue, Column]
    public int $id;

    #[Column]
    public string $name;

    #[Column]
    public int $voteCount = 0;

    /**
     * @var Collection<PollVote>
     */
    #[OneToMany(targetEntity: PollVote::class, mappedBy: 'choice')]
    public Collection $votes;

    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: Poll::class)]
    public Poll $poll;

    public function __construct()
    {
        $this->votes = new ArrayCollection();
    }

    /**
     * @return Collection<PollVote>
     */
    public function getUserVotes(User $user): Collection
    {
        $criteria = Criteria::create();
        $criteria->where(Criteria::expr()->eq('voter', $user));

        return $this->votes->matching($criteria);
    }

    public function hasUserVoted(User $user): bool
    {
        return !$this->getUserVotes($user)->isEmpty();
    }

    public function updateVoteCount(): void
    {
        if ($this->poll->isRemote) {
            ++$this->voteCount;
        } else {
            $this->voteCount = $this->votes->count();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Entity\Contracts\VotableInterface;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Column;

trait VotableTrait
{
    #[ORM\Column(type: 'integer')]
    private int $upVotes = 0;

    #[ORM\Column(type: 'integer')]
    private int $downVotes = 0;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apLikeCount = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apDislikeCount = null;

    #[Column(type: 'integer', nullable: true)]
    public ?int $apShareCount = null;

    public function countUpVotes(): int
    {
        return $this->apShareCount ?? $this->upVotes;
    }

    public function countDownVotes(): int
    {
        return $this->apDislikeCount ?? $this->downVotes;
    }

    public function countVotes(): int
    {
        return $this->countDownVotes() + $this->countUpVotes();
    }

    public function getUserChoice(User $user): int
    {
        $vote = $this->getUserVote($user);

        return $vote ? $vote->choice : VotableInterface::VOTE_NONE;
    }

    public function getUserVote(User $user): ?Vote
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        return $this->votes->matching($criteria)->first() ?: null;
    }

    public function updateVoteCounts(): self
    {
        $this->upVotes = $this->getUpVotes()->count();
        $this->downVotes = $this->getDownVotes()->count();

        return $this;
    }

    public function getUpVotes(): Collection
    {
        $this->votes->get(-1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('choice', self::VOTE_UP));

        return $this->votes->matching($criteria);
    }

    public function getDownVotes(): Collection
    {
        $this->votes->get(-1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('choice', self::VOTE_DOWN));

        return $this->votes->matching($criteria);
    }
}

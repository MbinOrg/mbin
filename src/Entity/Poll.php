<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;

#[Entity]
class Poll
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[Id, GeneratedValue, Column]
    private int $id;

    #[Column]
    public bool $multipleChoice = false;

    /**
     * @var int the number of individual users voting on this poll
     */
    #[Column(options: ['default' => 0])]
    public int $voterCount = 0;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $endDate;

    #[Column]
    public bool $isRemote = false;

    #[Column]
    public bool $sentNotifications = false;

    /**
     * @var Collection<PollVote>
     */
    #[OneToMany(targetEntity: PollVote::class, mappedBy: 'poll')]
    public Collection $votes;

    /** @var Collection<PollChoice> */
    #[OneToMany(targetEntity: PollChoice::class, mappedBy: 'poll')]
    public Collection $choices;

    #[OneToOne(targetEntity: Entry::class, mappedBy: 'poll')]
    public ?Entry $entry;

    #[OneToOne(targetEntity: EntryComment::class, mappedBy: 'poll')]
    public ?EntryComment $entryComment;

    #[OneToOne(targetEntity: Post::class, mappedBy: 'poll')]
    public ?Post $post;

    #[OneToOne(targetEntity: PostComment::class, mappedBy: 'poll')]
    public ?PostComment $postComment;

    public function __construct(?\DateTimeImmutable $endDate = null)
    {
        $this->createdAtTraitConstruct();
        $this->endDate = $endDate ?? new \DateTimeImmutable('now + 7days');
        $this->votes = new ArrayCollection();
        $this->choices = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function hasUserVoted(User $user): bool
    {
        return \count($this->getUserVotes($user)) > 0;
    }

    /**
     * @return PollVote[]
     */
    public function getUserVotes(User $user): array
    {
        $criteria = Criteria::create();
        $criteria->where(Criteria::expr()->eq('voter', $user));

        return $this->votes->matching($criteria)->toArray();
    }

    public function findChoice(string $name): ?PollChoice
    {
        $criteria = Criteria::create();
        $criteria->where(Criteria::expr()->eq('name', $name));

        return $this->choices->matching($criteria)->first() ?: null;
    }

    public function hasEnded(): bool
    {
        return new \DateTimeImmutable() > $this->endDate;
    }

    /**
     * @return array<array{name: string, userVoted: bool, percentage: float}>
     */
    public function getResultData(?User $user): array
    {
        $result = [];
        foreach ($this->choices as $choice) {
            $result[] = [
                'name' => $choice->name,
                'userVoted' => $user ? $choice->hasUserVoted($user) : false,
                'percentage' => $this->voterCount ? round(100 / $this->voterCount * $choice->voteCount, 2) : 0,
            ];
        }

        return $result;
    }

    public function updateVoterCount(): void
    {
        if ($this->isRemote) {
            ++$this->voterCount;
        } else {
            $voteUsers = [];
            foreach ($this->votes as $vote) {
                $voteUsers[$vote->voter->getId()] = $vote->voter;
            }
            $this->voterCount = \count($voteUsers);
        }
    }

    public function getSubject(): Entry|EntryComment|Post|PostComment|null
    {
        return $this->entry ?? $this->entryComment ?? $this->post ?? $this->postComment;
    }
}

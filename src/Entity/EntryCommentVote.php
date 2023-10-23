<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\VotableInterface;
use Doctrine\ORM\Mapping\AssociationOverride;
use Doctrine\ORM\Mapping\AssociationOverrides;
use Doctrine\ORM\Mapping\Cache;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[Table]
#[UniqueConstraint(name: 'user_entry_comment_vote_idx', columns: ['user_id', 'comment_id'])]
#[AssociationOverrides([
    new AssociationOverride(name: 'user', inversedBy: 'entryCommentVotes'),
])]
#[Cache(usage: 'NONSTRICT_READ_WRITE')]
class EntryCommentVote extends Vote
{
    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'votes')]
    #[JoinColumn(name: 'comment_id', nullable: false, onDelete: 'CASCADE')]
    public ?EntryComment $comment;

    #[Id]
    #[GeneratedValue(strategy: 'SEQUENCE')]
    #[SequenceGenerator(sequenceName: 'entry_comment_vote_id_seq')]
    #[Column(type: 'integer')]
    protected int $id;

    public function __construct(int $choice, User $user, EntryComment $comment)
    {
        parent::__construct($choice, $user, $comment->user);

        $this->comment = $comment;
    }

    public function getComment(): EntryComment
    {
        return $this->comment;
    }

    public function setComment(?EntryComment $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getSubject(): VotableInterface
    {
        return $this->comment;
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActivityInterface;
use App\Entity\Contracts\ContentInterface;
use App\Entity\Contracts\FavouriteInterface;
use App\Entity\Contracts\ReportInterface;
use App\Entity\Contracts\TagInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Contracts\VotableInterface;
use App\Entity\Traits\ActivityPubActivityTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\EditedAtTrait;
use App\Entity\Traits\VisibilityTrait;
use App\Entity\Traits\VotableTrait;
use App\Repository\EntryCommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Webmozart\Assert\Assert;

#[Entity(repositoryClass: EntryCommentRepository::class)]
#[Index(columns: ['up_votes'], name: 'entry_comment_up_votes_idx')]
#[Index(columns: ['last_active'], name: 'entry_comment_last_active_at_idx')]
#[Index(columns: ['created_at'], name: 'entry_comment_created_at_idx')]
#[Index(columns: ['body_ts'], name: 'entry_comment_body_ts_idx')]
class EntryComment implements VotableInterface, VisibilityInterface, ReportInterface, FavouriteInterface, TagInterface, ActivityPubActivityInterface
{
    use VotableTrait;
    use VisibilityTrait;
    use ActivityPubActivityTrait;
    use EditedAtTrait;
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[ManyToOne(targetEntity: User::class, inversedBy: 'entryComments')]
    #[JoinColumn(nullable: false)]
    public User $user;
    #[ManyToOne(targetEntity: Entry::class, inversedBy: 'comments')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Entry $entry;
    #[ManyToOne(targetEntity: Magazine::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Magazine $magazine;
    #[ManyToOne(targetEntity: Image::class, cascade: ['persist'])]
    #[JoinColumn(nullable: true)]
    public ?Image $image = null;
    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'children')]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?EntryComment $parent = null;
    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'nested')]
    #[JoinColumn(nullable: true)]
    public ?EntryComment $root = null;
    #[Column(type: 'text', length: 4500)]
    public ?string $body = null;
    #[Column(type: 'string', nullable: false)]
    public string $lang = 'en';
    #[Column(type: 'boolean', nullable: false)]
    public bool $isAdult = false;
    #[Column(type: 'integer', options: ['default' => 0])]
    public int $favouriteCount = 0;
    #[Column(type: 'datetimetz')]
    public ?\DateTime $lastActive = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $ip = null;
    #[Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $tags = null;
    #[Column(type: 'json', nullable: true)]
    public ?array $mentions = null;
    #[OneToMany(mappedBy: 'parent', targetEntity: EntryComment::class, orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'ASC'])]
    public Collection $children;
    #[OneToMany(mappedBy: 'root', targetEntity: EntryComment::class, orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'ASC'])]
    public Collection $nested;
    #[OneToMany(mappedBy: 'comment', targetEntity: EntryCommentVote::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $votes;
    #[OneToMany(mappedBy: 'entryComment', targetEntity: EntryCommentReport::class, cascade: ['remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $reports;
    #[OneToMany(mappedBy: 'entryComment', targetEntity: EntryCommentFavourite::class, cascade: ['remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $favourites;
    #[OneToMany(mappedBy: 'entryComment', targetEntity: EntryCommentCreatedNotification::class, cascade: ['remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $notifications;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;
    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => 'english'])]
    private $bodyTs;

    public function __construct(
        string $body,
        ?Entry $entry,
        User $user,
        EntryComment $parent = null,
        string $ip = null
    ) {
        $this->body = $body;
        $this->entry = $entry;
        $this->user = $user;
        $this->parent = $parent;
        $this->ip = $ip;
        $this->votes = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->favourites = new ArrayCollection();
        $this->notifications = new ArrayCollection();

        if ($parent) {
            $this->root = $parent->root ?? $parent;
        }

        $this->createdAtTraitConstruct();
        $this->updateLastActive();
    }

    public function updateLastActive(): void
    {
        $this->lastActive = \DateTime::createFromImmutable($this->createdAt);

        if (!$this->root) {
            return;
        }

        $this->root->lastActive = \DateTime::createFromImmutable($this->createdAt);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getApId(): ?string
    {
        return $this->apId;
    }

    public function addVote(Vote $vote): self
    {
        Assert::isInstanceOf($vote, EntryCommentVote::class);

        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setComment($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): self
    {
        Assert::isInstanceOf($vote, EntryCommentVote::class);

        if ($this->votes->removeElement($vote)) {
            // set the owning side to null (unless already changed)
            if ($vote->comment === $this) {
                $vote->setComment(null);
            }
        }

        return $this;
    }

    public function getChildrenRecursive(int &$startIndex = 0): \Traversable
    {
        foreach ($this->children as $child) {
            yield $startIndex++ => $child;
            yield from $child->getChildrenRecursive($startIndex);
        }
    }

    public function softDelete(): void
    {
        $this->visibility = VisibilityInterface::VISIBILITY_SOFT_DELETED;
    }

    public function trash(): void
    {
        $this->visibility = VisibilityInterface::VISIBILITY_TRASHED;
    }

    public function restore(): void
    {
        $this->visibility = VisibilityInterface::VISIBILITY_VISIBLE;
    }

    public function isAuthor(User $user): bool
    {
        return $user === $this->user;
    }

    public function getShortTitle(?int $length = 60): string
    {
        $body = wordwrap($this->body ?? '', $length);
        $body = explode("\n", $body);

        return trim($body[0]).(isset($body[1]) ? '...' : '');
    }

    public function getMagazine(): ?Magazine
    {
        return $this->magazine;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function updateCounts(): self
    {
        $this->favouriteCount = $this->favourites->count();

        return $this;
    }

    public function isFavored(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        return $this->favourites->matching($criteria)->count() > 0;
    }

    public function getTags(): array
    {
        return array_values($this->tags ?? []);
    }

    public function __sleep()
    {
        return [];
    }

    public function updateRanking(): void
    {
    }

    public function updateScore(): self
    {
        return $this;
    }

    public function getParentSubject(): ?ContentInterface
    {
        return $this->entry;
    }
}

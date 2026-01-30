<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Contracts\ApiResourceInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Traits\ActivityPubActorTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\VisibilityTrait;
use App\Repository\MagazineRepository;
use App\Service\MagazineManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity(repositoryClass: MagazineRepository::class)]
#[Index(columns: ['visibility', 'is_adult'], name: 'magazine_visibility_adult_idx')]
#[Index(columns: ['is_adult'], name: 'magazine_adult_idx')]
#[Index(columns: ['name_ts'], name: 'magazine_name_ts')]
#[Index(columns: ['title_ts'], name: 'magazine_title_ts')]
#[Index(columns: ['description_ts'], name: 'magazine_description_ts')]
#[UniqueConstraint(name: 'magazine_name_idx', columns: ['name'])]
#[UniqueConstraint(name: 'magazine_ap_id_idx', columns: ['ap_id'])]
#[UniqueConstraint(name: 'magazine_ap_profile_id_idx', columns: ['ap_profile_id'])]
#[UniqueConstraint(name: 'magazine_ap_public_url_idx', columns: ['ap_public_url'])]
class Magazine implements VisibilityInterface, ActivityPubActorInterface, ApiResourceInterface
{
    use ActivityPubActorTrait;
    use VisibilityTrait;
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    public const MAX_DESCRIPTION_LENGTH = 10000;
    public const MAX_RULES_LENGTH = 10000;

    #[ManyToOne(targetEntity: Image::class, cascade: ['persist'])]
    #[JoinColumn(nullable: true)]
    public ?Image $icon = null;
    #[ManyToOne(targetEntity: Image::class, cascade: ['persist'])]
    #[JoinColumn(nullable: true)]
    public ?Image $banner = null;
    #[Column(type: 'string', nullable: false)]
    public string $name;
    #[Column(type: 'string')]
    public ?string $title;
    #[Column(type: 'text', length: self::MAX_DESCRIPTION_LENGTH, nullable: true)]
    public ?string $description = null;
    #[Column(type: 'text', length: self::MAX_RULES_LENGTH, nullable: true)]
    public ?string $rules = null;
    #[Column(type: 'boolean', nullable: false, options: ['default' => false])]
    public bool $postingRestrictedToMods = false;
    #[Column(type: 'integer', nullable: false)]
    public int $subscriptionsCount = 0;
    #[Column(type: 'integer', nullable: false)]
    public int $entryCount = 0;
    #[Column(type: 'integer', nullable: false)]
    public int $entryCommentCount = 0;
    #[Column(type: 'integer', nullable: false)]
    public int $postCount = 0;
    #[Column(type: 'integer', nullable: false)]
    public int $postCommentCount = 0;
    #[Column(type: 'boolean', nullable: false)]
    public bool $isAdult = false;
    #[Column(type: 'text', nullable: true)]
    public ?string $customCss = null;
    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $lastActive = null;
    /**
     * @var \DateTime|null this is set if this is a remote magazine.
     *                     This is the last time we had an update from the origin of the magazine
     */
    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $lastOriginUpdate = null;
    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $markedForDeletionAt = null;
    #[Column(type: Types::JSONB, nullable: true)]
    public ?array $tags = null;
    #[OneToMany(mappedBy: 'magazine', targetEntity: Moderator::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $moderators;
    #[OneToMany(mappedBy: 'magazine', targetEntity: MagazineOwnershipRequest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $ownershipRequests;
    #[OneToMany(mappedBy: 'magazine', targetEntity: ModeratorRequest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $moderatorRequests;
    #[OneToMany(mappedBy: 'magazine', targetEntity: Entry::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $entries;
    #[OneToMany(mappedBy: 'magazine', targetEntity: Post::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $posts;
    #[OneToMany(mappedBy: 'magazine', targetEntity: MagazineSubscription::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $subscriptions;
    #[OneToMany(mappedBy: 'magazine', targetEntity: MagazineBan::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $bans;
    #[OneToMany(mappedBy: 'magazine', targetEntity: Report::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $reports;
    #[OneToMany(mappedBy: 'magazine', targetEntity: Badge::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['id' => 'DESC'])]
    public Collection $badges;
    #[OneToMany(mappedBy: 'magazine', targetEntity: MagazineLog::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $logs;

    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => null])]
    private ?string $nameTs;
    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => null])]
    private ?string $titleTs;
    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => null])]
    private ?string $descriptionTs;

    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    public function __construct(
        string $name,
        string $title,
        ?User $user,
        ?string $description,
        ?string $rules,
        bool $isAdult,
        bool $postingRestrictedToMods,
        ?Image $icon,
        ?Image $banner = null,
    ) {
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->rules = $rules;
        $this->isAdult = $isAdult;
        $this->postingRestrictedToMods = $postingRestrictedToMods;
        $this->icon = $icon;
        $this->banner = $banner;
        $this->moderators = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->bans = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->logs = new ArrayCollection();
        $this->moderatorRequests = new ArrayCollection();
        $this->ownershipRequests = new ArrayCollection();

        if (null !== $user) {
            $this->addModerator(new Moderator($this, $user, null, true, true));
        }

        $this->createdAtTraitConstruct();
    }

    /**
     * Only use this to add a moderator if you don't want that action to be federated.
     * If you want this action to be federated, use @see MagazineManager::addModerator().
     *
     * @return $this
     */
    public function addModerator(Moderator $moderator): self
    {
        if (!$this->moderators->contains($moderator)) {
            $this->moderators->add($moderator);
            $moderator->magazine = $this;
        }

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getApId(): ?string
    {
        return $this->apId;
    }

    public function userIsModerator(User $user): bool
    {
        $user->moderatorTokens->get(-1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $this))
            ->andWhere(Criteria::expr()->eq('isConfirmed', true));

        return !$user->moderatorTokens->matching($criteria)->isEmpty();
    }

    public function getUserAsModeratorOrNull(User $user): ?Moderator
    {
        $user->moderatorTokens->get(-1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $this))
            ->andWhere(Criteria::expr()->eq('isConfirmed', true));

        $col = $user->moderatorTokens->matching($criteria);
        if (!$col->isEmpty()) {
            return $col->first();
        }

        return null;
    }

    public function userIsOwner(User $user): bool
    {
        $user->moderatorTokens->get(-1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $this))
            ->andWhere(Criteria::expr()->eq('isOwner', true));

        return !$user->moderatorTokens->matching($criteria)->isEmpty();
    }

    public function isAbandoned(): bool
    {
        return !$this->apId and (null === $this->getOwner() || $this->getOwner()->lastActive < new \DateTime('-1 month'));
    }

    public function getOwnerModerator(): ?Moderator
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('isOwner', true));

        $res = $this->moderators->matching($criteria)->first();
        if (false !== $res) {
            return $res;
        }

        return null;
    }

    public function getOwner(): ?User
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('isOwner', true));

        $res = $this->moderators->matching($criteria)->first();
        if (false !== $res) {
            return $res->user;
        }

        return null;
    }

    public function getModeratorCount(): int
    {
        return $this->moderators->count();
    }

    public function addEntry(Entry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->magazine = $this;
        }

        $this->updateEntryCounts();

        return $this;
    }

    public function updateEntryCounts(): self
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->eq('visibility', Entry::VISIBILITY_VISIBLE));

        $this->entryCount = $this->entries->matching($criteria)->count();

        return $this;
    }

    public function removeEntry(Entry $entry): self
    {
        if ($this->entries->removeElement($entry)) {
            if ($entry->magazine === $this) {
                $entry->magazine = null;
            }
        }

        $this->updateEntryCounts();

        return $this;
    }

    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->magazine = $this;
        }

        $this->updatePostCounts();

        return $this;
    }

    public function updatePostCounts(): self
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->eq('visibility', Entry::VISIBILITY_VISIBLE));

        $this->postCount = $this->posts->matching($criteria)->count();

        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            if ($post->magazine === $this) {
                $post->magazine = null;
            }
        }
        $this->updatePostCounts();

        return $this;
    }

    public function subscribe(User $user): self
    {
        if (!$this->isSubscribed($user)) {
            $this->subscriptions->add($sub = new MagazineSubscription($user, $this));
            $sub->magazine = $this;
        }

        $this->updateSubscriptionsCount();

        return $this;
    }

    public function isSubscribed(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        return $this->subscriptions->matching($criteria)->count() > 0;
    }

    public function updateSubscriptionsCount(): void
    {
        if (null !== $this->apFollowersCount) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->gt('createdAt', \DateTimeImmutable::createFromMutable($this->apFetchedAt)));

            $newSubscribers = $this->subscriptions->matching($criteria)->count();
            $this->subscriptionsCount = $this->apFollowersCount + $newSubscribers;
        } else {
            $this->subscriptionsCount = $this->subscriptions->count();
        }
    }

    public function unsubscribe(User $user): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $user));

        $subscription = $this->subscriptions->matching($criteria)->first();

        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->magazine === $this) {
                $subscription->magazine = null;
            }
        }

        $this->updateSubscriptionsCount();
    }

    public function softDelete(): void
    {
        $this->markedForDeletionAt = new \DateTime('now + 30days');
        $this->visibility = VisibilityInterface::VISIBILITY_SOFT_DELETED;
    }

    public function trash(): void
    {
        $this->visibility = VisibilityInterface::VISIBILITY_TRASHED;
    }

    public function restore(): void
    {
        $this->markedForDeletionAt = null;
        $this->visibility = VisibilityInterface::VISIBILITY_VISIBLE;
    }

    public function addBan(User $user, User $bannedBy, ?string $reason, ?\DateTimeInterface $expiredAt): ?MagazineBan
    {
        $ban = $this->isBanned($user);

        if (!$ban) {
            $this->bans->add($ban = new MagazineBan($this, $user, $bannedBy, $reason, $expiredAt));
            $ban->magazine = $this;
        } else {
            return null;
        }

        return $ban;
    }

    public function isBanned(User $user): bool
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->gt('expiredAt', new \DateTimeImmutable()))
            ->orWhere(Criteria::expr()->isNull('expiredAt'))
            ->andWhere(Criteria::expr()->eq('user', $user));

        return $this->bans->matching($criteria)->count() > 0;
    }

    public function removeBan(MagazineBan $ban): self
    {
        if ($this->bans->removeElement($ban)) {
            if ($ban->magazine === $this) {
                $ban->magazine = null;
            }
        }

        return $this;
    }

    public function unban(User $user): MagazineBan
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->gt('expiredAt', new \DateTimeImmutable()))
            ->orWhere(Criteria::expr()->isNull('expiredAt'))
            ->andWhere(Criteria::expr()->eq('user', $user));

        /**
         * @var MagazineBan $ban
         */
        $ban = $this->bans->matching($criteria)->first();
        $ban->expiredAt = new \DateTimeImmutable('-10 seconds');

        return $ban;
    }

    public function addBadge(Badge ...$badges): self
    {
        foreach ($badges as $badge) {
            if (!$this->badges->contains($badge)) {
                $this->badges->add($badge);
            }
        }

        return $this;
    }

    public function removeBadge(Badge $badge): self
    {
        $this->badges->removeElement($badge);

        return $this;
    }

    public function addLog(MagazineLog $log): void
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
        }
    }

    public function __sleep()
    {
        return [];
    }

    public function getApName(): string
    {
        return $this->name;
    }

    public function hasSameHostAsUser(User $actor): bool
    {
        if (!$actor->apId and !$this->apId) {
            return true;
        }

        if ($actor->apId and $this->apId) {
            return parse_url($actor->apId, PHP_URL_HOST) === parse_url($this->apId, PHP_URL_HOST);
        }

        return false;
    }

    public function canUpdateMagazine(User $actor): bool
    {
        if (null === $this->apId) {
            return $actor->isAdmin() || $actor->isModerator() || $this->userIsModerator($actor);
        } else {
            return $this->apDomain === $actor->apDomain || $this->userIsModerator($actor);
        }
    }

    /**
     * @param Magazine|User $actor the actor trying to create an Entry
     *
     * @return bool false if the user is not restricted, true if the user is restricted
     */
    public function isActorPostingRestricted(Magazine|User $actor): bool
    {
        if (!$this->postingRestrictedToMods) {
            return false;
        }
        if ($actor instanceof User) {
            if (null !== $this->apId && $this->apDomain === $actor->apDomain) {
                return false;
            }

            if ((null === $this->apId && ($actor->isAdmin() || $actor->isModerator())) || $this->userIsModerator($actor)) {
                return false;
            }
        }

        return true;
    }

    public function getContentCount(): int
    {
        return $this->entryCount + $this->entryCommentCount + $this->postCount + $this->postCommentCount;
    }
}

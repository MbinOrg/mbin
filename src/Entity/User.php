<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ActivityPubActorInterface;
use App\Entity\Contracts\ApiResourceInterface;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Traits\ActivityPubActorTrait;
use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\VisibilityTrait;
use App\Enums\EApplicationStatus;
use App\Enums\EDirectMessageSettings;
use App\Enums\ESortOptions;
use App\Repository\UserRepository;
use App\Service\ActivityPub\ApHttpClientInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Entity(repositoryClass: UserRepository::class)]
#[Table(name: '`user`')]
#[Index(columns: ['visibility'], name: 'user_visibility_idx')]
#[Index(columns: ['username_ts'], name: 'user_username_ts')]
#[Index(columns: ['about_ts'], name: 'user_about_ts')]
#[UniqueConstraint(name: 'user_email_idx', columns: ['email'])]
#[UniqueConstraint(name: 'user_username_idx', columns: ['username'])]
#[UniqueConstraint(name: 'user_ap_id_idx', columns: ['ap_id'])]
#[UniqueConstraint(name: 'user_ap_profile_id_idx', columns: ['ap_profile_id'])]
#[UniqueConstraint(name: 'user_ap_public_url_idx', columns: ['ap_public_url'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, VisibilityInterface, TwoFactorInterface, BackupCodeInterface, EquatableInterface, ActivityPubActorInterface, ApiResourceInterface
{
    use ActivityPubActorTrait;
    use VisibilityTrait;
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    public const THEME_LIGHT = 'light';
    public const THEME_DARK = 'dark';
    public const THEME_AUTO = 'auto';
    public const THEME_OPTIONS = [
        self::THEME_AUTO,
        self::THEME_DARK,
        self::THEME_LIGHT,
    ];

    public const MODE_NORMAL = 'normal';
    public const MODE_TURBO = 'turbo';

    public const HOMEPAGE_ALL = 'front';
    public const HOMEPAGE_SUB = 'front_subscribed';
    public const HOMEPAGE_MOD = 'front_moderated';
    public const HOMEPAGE_FAV = 'front_favourite';
    public const HOMEPAGE_OPTIONS = [
        self::HOMEPAGE_ALL,
        self::HOMEPAGE_SUB,
        self::HOMEPAGE_MOD,
        self::HOMEPAGE_FAV,
    ];

    public const USER_TYPE_PERSON = 'Person';
    public const USER_TYPE_SERVICE = 'Service';
    public const USER_TYPE_ORG = 'Organization';
    public const USER_TYPE_APP = 'Application';
    public const USER_TYPES = [
        self::USER_TYPE_PERSON,
        self::USER_TYPE_SERVICE,
        self::USER_TYPE_ORG,
        self::USER_TYPE_APP,
    ];

    #[ManyToOne(targetEntity: Image::class, cascade: ['persist'])]
    #[JoinColumn(nullable: true)]
    public ?Image $avatar = null;
    #[ManyToOne(targetEntity: Image::class, cascade: ['persist'])]
    #[JoinColumn(nullable: true)]
    public ?Image $cover = null;
    #[Column(type: 'string', unique: true, nullable: false)]
    public string $email;
    #[Column(type: 'string', unique: true, nullable: false)]
    public string $username;
    #[Column(type: 'json', nullable: false, options: ['jsonb' => true])]
    public array $roles = [];
    #[Column(type: 'integer', nullable: false)]
    public int $followersCount = 0;
    #[Column(type: 'string', nullable: false, options: ['default' => self::HOMEPAGE_ALL])]
    public string $homepage = self::HOMEPAGE_ALL;
    #[Column(type: 'enumSortOptions', nullable: false, options: ['default' => ESortOptions::Hot->value])]
    public string $frontDefaultSort = ESortOptions::Hot->value;
    #[Column(type: 'enumSortOptions', nullable: false, options: ['default' => ESortOptions::Hot->value])]
    public string $commentDefaultSort = ESortOptions::Hot->value;
    #[Column(type: 'enumDirectMessageSettings', nullable: false, options: ['default' => EDirectMessageSettings::Everyone->value])]
    public string $directMessageSetting = EDirectMessageSettings::Everyone->value;
    #[Column(type: 'text', nullable: true)]
    public ?string $about = null;
    #[Column(type: 'datetimetz')]
    public ?\DateTime $lastActive = null;
    #[Column(type: 'datetimetz', nullable: true)]
    public ?\DateTime $markedForDeletionAt = null;
    #[Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    public ?array $fields = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthAzureId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthGithubId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthGoogleId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthFacebookId = null;
    #[Column(name: 'oauth_privacyportal_id', type: 'string', nullable: true)]
    public ?string $oauthPrivacyPortalId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthKeycloakId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthSimpleLoginId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthDiscordId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthZitadelId = null;
    #[Column(type: 'string', nullable: true)]
    public ?string $oauthAuthentikId = null;
    #[Column(type: 'boolean', nullable: false, options: ['default' => true])]
    public bool $hideAdult = true;
    #[Column(type: 'json', nullable: false, options: ['jsonb' => true, 'default' => '[]'])]
    public array $preferredLanguages = [];
    #[Column(type: 'array', nullable: true)]
    public ?array $featuredMagazines = null;
    #[Column(type: 'boolean', nullable: false, options: ['default' => true])]
    public bool $showProfileSubscriptions = false;
    #[Column(type: 'boolean', nullable: false, options: ['default' => true])]
    public bool $showProfileFollowings = true;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewEntry = false;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewEntryReply = true;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewEntryCommentReply = true;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewPost = false;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewPostReply = true;
    #[Column(type: 'boolean', nullable: false)]
    public bool $notifyOnNewPostCommentReply = true;
    #[Column(type: 'boolean', nullable: false, options: ['default' => true])]
    public bool $notifyOnUserSignup = true;
    #[Column(type: 'boolean', nullable: false, options: ['default' => false])]
    public bool $addMentionsEntries = false;
    #[Column(type: 'boolean', nullable: false, options: ['default' => true])]
    public bool $addMentionsPosts = true;
    #[Column(type: 'boolean', nullable: false, options: ['default' => false])]
    public bool $isBanned = false;
    #[Column(type: 'boolean', nullable: false)]
    public bool $isVerified = false;
    #[Column(type: 'boolean', nullable: false, options: ['default' => false])]
    public bool $isDeleted = false;
    #[Column(type: 'text', nullable: true)]
    public ?string $customCss = null;
    #[Column(type: 'boolean', nullable: false, options: ['default' => false])]
    public bool $ignoreMagazinesCustomCss = false;
    #[OneToMany(mappedBy: 'user', targetEntity: Moderator::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $moderatorTokens;
    #[OneToMany(mappedBy: 'user', targetEntity: MagazineOwnershipRequest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $magazineOwnershipRequests;
    #[OneToMany(mappedBy: 'user', targetEntity: ModeratorRequest::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $moderatorRequests;
    #[OneToMany(mappedBy: 'user', targetEntity: Entry::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $entries;
    #[OneToMany(mappedBy: 'user', targetEntity: EntryVote::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $entryVotes;
    #[OneToMany(mappedBy: 'user', targetEntity: EntryComment::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $entryComments; // @todo
    #[OneToMany(mappedBy: 'user', targetEntity: EntryCommentVote::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $entryCommentVotes;
    #[OneToMany(mappedBy: 'user', targetEntity: Post::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $posts;
    #[OneToMany(mappedBy: 'user', targetEntity: PostVote::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $postVotes;
    #[OneToMany(mappedBy: 'user', targetEntity: PostComment::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $postComments;
    #[OneToMany(mappedBy: 'user', targetEntity: PostCommentVote::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $postCommentVotes;
    #[OneToMany(mappedBy: 'user', targetEntity: MagazineSubscription::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $subscriptions;
    #[OneToMany(mappedBy: 'user', targetEntity: DomainSubscription::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $subscribedDomains;
    #[OneToMany(mappedBy: 'follower', targetEntity: UserFollow::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $follows;
    #[OneToMany(mappedBy: 'following', targetEntity: UserFollow::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $followers;
    #[OneToMany(mappedBy: 'blocker', targetEntity: UserBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $blocks;
    #[OneToMany(mappedBy: 'blocked', targetEntity: UserBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public ?Collection $blockers;
    #[OneToMany(mappedBy: 'user', targetEntity: MagazineBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $blockedMagazines;
    #[OneToMany(mappedBy: 'user', targetEntity: DomainBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $blockedDomains;
    #[OneToMany(mappedBy: 'reporting', targetEntity: Report::class, cascade: ['persist'], fetch: 'EXTRA_LAZY')]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $reports;
    #[OneToMany(mappedBy: 'user', targetEntity: Favourite::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $favourites;
    #[OneToMany(mappedBy: 'reported', targetEntity: Report::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $violations;
    #[OneToMany(mappedBy: 'user', targetEntity: Notification::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['createdAt' => 'DESC'])]
    public Collection $notifications;
    #[OneToMany(mappedBy: 'user', targetEntity: UserPushSubscription::class, fetch: 'EXTRA_LAZY')]
    public Collection $pushSubscriptions;
    #[OneToMany(mappedBy: 'user', targetEntity: BookmarkList::class, fetch: 'EXTRA_LAZY')]
    public Collection $bookmarkLists;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;
    #[Column(type: 'string', nullable: false)]
    private string $password;
    #[Column(type: 'string', nullable: true)]
    private ?string $totpSecret = null;
    #[Column(type: 'json', nullable: false, options: ['jsonb' => true, 'default' => '[]'])]
    private array $totpBackupCodes = [];
    #[OneToMany(mappedBy: 'user', targetEntity: OAuth2UserConsent::class, orphanRemoval: true)]
    private Collection $oAuth2UserConsents;
    #[Column(type: 'string', nullable: false, options: ['default' => self::USER_TYPE_PERSON])]
    public string $type;

    #[Column(type: 'text', nullable: true)]
    public ?string $applicationText;

    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => null])]
    private ?string $usernameTs;
    #[Column(type: 'text', nullable: true, insertable: false, updatable: false, options: ['default' => null])]
    private ?string $aboutTs;

    #[Column(type: 'enumApplicationStatus', nullable: false, options: ['default' => EApplicationStatus::Approved->value])]
    private string $applicationStatus;

    public function __construct(
        string $email,
        string $username,
        string $password,
        string $type,
        ?string $apProfileId = null,
        ?string $apId = null,
        EApplicationStatus $applicationStatus = EApplicationStatus::Approved,
        ?string $applicationText = null,
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->username = $username;
        $this->type = $type;
        $this->apProfileId = $apProfileId;
        $this->apId = $apId;
        $this->moderatorTokens = new ArrayCollection();
        $this->magazineOwnershipRequests = new ArrayCollection();
        $this->moderatorRequests = new ArrayCollection();
        $this->entries = new ArrayCollection();
        $this->entryVotes = new ArrayCollection();
        $this->entryComments = new ArrayCollection();
        $this->entryCommentVotes = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->postVotes = new ArrayCollection();
        $this->postComments = new ArrayCollection();
        $this->postCommentVotes = new ArrayCollection();
        $this->subscriptions = new ArrayCollection();
        $this->subscribedDomains = new ArrayCollection();
        $this->follows = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->blocks = new ArrayCollection();
        $this->blockers = new ArrayCollection();
        $this->blockedMagazines = new ArrayCollection();
        $this->blockedDomains = new ArrayCollection();
        $this->reports = new ArrayCollection();
        $this->favourites = new ArrayCollection();
        $this->violations = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->lastActive = new \DateTime();
        $this->createdAtTraitConstruct();
        $this->oAuth2UserConsents = new ArrayCollection();
        $this->setApplicationStatus($applicationStatus);
        $this->applicationText = $applicationText;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getApId(): ?string
    {
        return $this->apId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setOrRemoveAdminRole(bool $remove = false): self
    {
        $this->roles = ['ROLE_ADMIN'];

        if ($remove) {
            $this->roles = [];
        }

        return $this;
    }

    public function setOrRemoveModeratorRole(bool $remove = false): self
    {
        $this->roles = ['ROLE_MODERATOR'];

        if ($remove) {
            $this->roles = [];
        }

        return $this;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
        return null;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        //         $this->plainPassword = null;
    }

    public function getModeratedMagazines(): Collection
    {
        // Tokens
        $this->moderatorTokens->get(-1);
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->eq('isConfirmed', true));
        $tokens = $this->moderatorTokens->matching($criteria);

        // Magazines
        $magazines = $tokens->map(fn ($token) => $token->magazine);
        $criteria = Criteria::create()
            ->orderBy(['lastActive' => Order::Descending]);

        return $magazines->matching($criteria);
    }

    public function addEntry(Entry $entry): self
    {
        if ($entry->user !== $this) {
            throw new \DomainException('Entry must belong to user');
        }

        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
        }

        return $this;
    }

    public function addEntryComment(EntryComment $comment): self
    {
        if (!$this->entryComments->contains($comment)) {
            $this->entryComments->add($comment);
            $comment->user = $this;
        }

        return $this;
    }

    public function addPost(Post $post): self
    {
        if ($post->user !== $this) {
            throw new \DomainException('Post must belong to user');
        }

        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
        }

        return $this;
    }

    public function addPostComment(PostComment $comment): self
    {
        if (!$this->entryComments->contains($comment)) {
            $this->entryComments->add($comment);
            $comment->user = $this;
        }

        return $this;
    }

    public function addSubscription(MagazineSubscription $subscription): self
    {
        if (!$this->subscriptions->contains($subscription)) {
            $this->subscriptions->add($subscription);
            $subscription->setUser($this);
        }

        return $this;
    }

    public function removeSubscription(MagazineSubscription $subscription): self
    {
        if ($this->subscriptions->removeElement($subscription)) {
            if ($subscription->user === $this) {
                $subscription->user = null;
            }
        }

        return $this;
    }

    public function isFollower(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('follower', $this));

        return $user->followers->matching($criteria)->count() > 0;
    }

    public function follow(User $following): self
    {
        $this->unblock($following);

        if (!$this->isFollowing($following)) {
            $this->followers->add($follower = new UserFollow($this, $following));

            if (!$following->followers->contains($follower)) {
                $following->followers->add($follower);
            }
        }

        $following->updateFollowCounts();

        return $this;
    }

    public function unblock(User $blocked): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('blocked', $blocked));

        /**
         * @var $userBlock UserBlock
         */
        $userBlock = $this->blocks->matching($criteria)->first();

        if ($this->blocks->removeElement($userBlock)) {
            if ($userBlock->blocker === $this) {
                $blocked->blockers->removeElement($this);
            }
        }
    }

    public function isFollowing(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('following', $user));

        return $this->follows->matching($criteria)->count() > 0;
    }

    public function updateFollowCounts(): void
    {
        if (null !== $this->apFollowersCount) {
            $criteria = Criteria::create();
            if ($this->apFetchedAt) {
                $criteria->where(Criteria::expr()->gt('createdAt', \DateTimeImmutable::createFromMutable($this->apFetchedAt)));
            }

            $newFollowers = $this->followers->matching($criteria)->count();
            $this->followersCount = $this->apFollowersCount + $newFollowers;
        } else {
            $this->followersCount = $this->followers->count();
        }
    }

    public function unfollow(User $following): void
    {
        $followingUser = $following;

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('following', $following));

        /**
         * @var $following UserFollow
         */
        $following = $this->follows->matching($criteria)->first();

        if ($this->follows->removeElement($following)) {
            if ($following->follower === $this) {
                $following->follower = null;
                $followingUser->followers->removeElement($following);
            }
        }

        $followingUser->updateFollowCounts();
    }

    public function toggleTheme(): self
    {
        $this->theme = self::THEME_LIGHT === $this->theme ? self::THEME_DARK : self::THEME_LIGHT;

        return $this;
    }

    public function isBlocker(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('blocker', $user));

        return $user->blockers->matching($criteria)->count() > 0;
    }

    public function block(User $blocked): self
    {
        if (!$this->isBlocked($blocked)) {
            $this->blocks->add($userBlock = new UserBlock($this, $blocked));

            if (!$blocked->blockers->contains($userBlock)) {
                $blocked->blockers->add($userBlock);
            }
        }

        return $this;
    }

    /**
     * Returns whether or not the given user is blocked by the user this method is called on.
     */
    public function isBlocked(User $user): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('blocked', $user));

        return $this->blocks->matching($criteria)->count() > 0;
    }

    public function blockMagazine(Magazine $magazine): self
    {
        if (!$this->isBlockedMagazine($magazine)) {
            $this->blockedMagazines->add(new MagazineBlock($this, $magazine));
        }

        return $this;
    }

    public function isBlockedMagazine(Magazine $magazine): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $magazine));

        return $this->blockedMagazines->matching($criteria)->count() > 0;
    }

    public function unblockMagazine(Magazine $magazine): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $magazine));

        /**
         * @var $magazineBlock MagazineBlock
         */
        $magazineBlock = $this->blockedMagazines->matching($criteria)->first();

        if ($this->blockedMagazines->removeElement($magazineBlock)) {
            if ($magazineBlock->user === $this) {
                $magazineBlock->magazine = null;
                $this->blockedMagazines->removeElement($magazineBlock);
            }
        }
    }

    public function blockDomain(Domain $domain): self
    {
        if (!$this->isBlockedDomain($domain)) {
            $this->blockedDomains->add(new DomainBlock($this, $domain));
        }

        return $this;
    }

    public function isBlockedDomain(Domain $domain): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('domain', $domain));

        return $this->blockedDomains->matching($criteria)->count() > 0;
    }

    public function unblockDomain(Domain $domain): void
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('domain', $domain));

        /**
         * @var $domainBlock DomainBlock
         */
        $domainBlock = $this->blockedDomains->matching($criteria)->first();

        if ($this->blockedDomains->removeElement($domainBlock)) {
            if ($domainBlock->user === $this) {
                $domainBlock->domain = null;
                $this->blockedMagazines->removeElement($domainBlock);
            }
        }
    }

    public function getNewNotifications(): Collection
    {
        return $this->notifications->matching($this->getNewNotificationsCriteria());
    }

    private function getNewNotificationsCriteria(): Criteria
    {
        return Criteria::create()
            ->where(Criteria::expr()->eq('status', Notification::STATUS_NEW));
    }

    public function getNewEntryNotifications(User $user, Entry $entry): ?Notification
    {
        $criteria = $this->getNewNotificationsCriteria()
            ->andWhere(Criteria::expr()->eq('user', $user))
            ->andWhere(Criteria::expr()->eq('entry', $entry))
            ->andWhere(Criteria::expr()->eq('type', 'new_entry'));

        return $this->notifications->matching($criteria)->first();
    }

    public function countNewNotifications(): int
    {
        return $this->notifications
            ->matching($this->getNewNotificationsCriteria())
            ->filter(fn ($notification) => 'message_notification' !== $notification->getType())
            ->count();
    }

    public function countNewMessages(): int
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('status', Notification::STATUS_NEW));

        return $this->notifications
            ->matching($criteria)
            ->filter(fn ($notification) => 'message_notification' === $notification->getType())
            ->count();
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->getRoles());
    }

    public function isModerator(): bool
    {
        return \in_array('ROLE_MODERATOR', $this->getRoles());
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function isAccountDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function __call(string $name, array $arguments)
    {
        // TODO: Implement @method string getUserIdentifier()
    }

    /**
     * This method is used by Symfony to determine whether a session needs to be refreshed.
     * Every security relevant information needs to be in there.
     * In order to check these parameters you need to add them to the __serialize function.
     *
     * @see User::__serialize()
     */
    public function isEqualTo(UserInterface $user): bool
    {
        $pa = PropertyAccess::createPropertyAccessor();
        $theirTotpSecret = $pa->getValue($user, 'totpSecret') ?? '';

        return $pa->getValue($user, 'isBanned') === $this->isBanned
            && $pa->getValue($user, 'isDeleted') === $this->isDeleted
            && $pa->getValue($user, 'markedForDeletionAt') === $this->markedForDeletionAt
            && $pa->getValue($user, 'username') === $this->username
            && $pa->getValue($user, 'password') === $this->password
            && ($theirTotpSecret === $this->totpSecret || $theirTotpSecret === hash('sha256', $this->totpSecret) || hash('sha256', $theirTotpSecret) === $this->totpSecret);
    }

    public function getApName(): string
    {
        return $this->username;
    }

    public function isActiveNow(): bool
    {
        $delay = new \DateTime('1 day ago');

        return $this->lastActive > $delay;
    }

    public function getShowProfileFollowings(): bool
    {
        if ($this->apId) {
            return true;
        }

        return $this->showProfileFollowings;
    }

    public function getShowProfileSubscriptions(): bool
    {
        if ($this->apId) {
            return false;
        }

        return $this->showProfileSubscriptions;
    }

    /**
     * @return Collection<int, OAuth2UserConsent>
     */
    public function getOAuth2UserConsents(): Collection
    {
        return $this->oAuth2UserConsents;
    }

    public function addOAuth2UserConsent(OAuth2UserConsent $oAuth2UserConsent): self
    {
        if (!$this->oAuth2UserConsents->contains($oAuth2UserConsent)) {
            $this->oAuth2UserConsents->add($oAuth2UserConsent);
            $oAuth2UserConsent->setUser($this);
        }

        return $this;
    }

    public function removeOAuth2UserConsent(OAuth2UserConsent $oAuth2UserConsent): self
    {
        if ($this->oAuth2UserConsents->removeElement($oAuth2UserConsent)) {
            // set the owning side to null (unless already changed)
            if ($oAuth2UserConsent->getUser() === $this) {
                $oAuth2UserConsent->setUser(null);
            }
        }

        return $this;
    }

    public function isSsoControlled(): bool
    {
        return $this->oauthAzureId || $this->oauthGithubId || $this->oauthGoogleId || $this->oauthDiscordId || $this->oauthFacebookId || $this->oauthKeycloakId || $this->oauthSimpleLoginId || $this->oauthZitadelId || $this->oauthAuthentikId || $this->oauthPrivacyPortalId;
    }

    public function getCustomCss(): ?string
    {
        return $this->customCss;
    }

    public function setCustomCss(?string $customCss): static
    {
        $this->customCss = $customCss;

        return $this;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return (bool) $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    /**
     * @param string[]|null $codes
     */
    public function setBackupCodes(?array $codes): void
    {
        $this->totpBackupCodes = $codes;
    }

    public function isBackupCode(string $code): bool
    {
        return \in_array($code, $this->totpBackupCodes);
    }

    public function invalidateBackupCode(string $code): void
    {
        $this->totpBackupCodes = array_values(
            array_filter($this->totpBackupCodes, function ($existingCode) use ($code) {
                return $code !== $existingCode;
            })
        );
    }

    public function softDelete(): void
    {
        $this->markedForDeletionAt = new \DateTime('now + 30days');
        $this->visibility = VisibilityInterface::VISIBILITY_SOFT_DELETED;
        $this->isDeleted = true;
    }

    public function isSoftDeleted(): bool
    {
        return self::VISIBILITY_SOFT_DELETED === $this->visibility;
    }

    public function trash(): void
    {
        $this->visibility = self::VISIBILITY_TRASHED;
    }

    public function isTrashed(): bool
    {
        return self::VISIBILITY_TRASHED === $this->visibility;
    }

    public function restore(): void
    {
        $this->markedForDeletionAt = null;
        $this->visibility = VisibilityInterface::VISIBILITY_VISIBLE;
        $this->isDeleted = false;
    }

    public function hasModeratorRequest(Magazine $magazine): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $magazine));

        return $this->moderatorRequests->matching($criteria)->count() > 0;
    }

    public function hasMagazineOwnershipRequest(Magazine $magazine): bool
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('magazine', $magazine));

        return $this->magazineOwnershipRequests->matching($criteria)->count() > 0;
    }

    public function getFollowerUrl(ApHttpClientInterface $client, UrlGeneratorInterface $urlGenerator, bool $isRemote): ?string
    {
        if ($isRemote) {
            $actorObject = $client->getActorObject($this->apProfileId);
            if ($actorObject and isset($actorObject['followers']) and \is_string($actorObject['followers'])) {
                return $actorObject['followers'];
            }

            return null;
        } else {
            return $urlGenerator->generate(
                'ap_user_followers',
                ['username' => $this->username],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }
    }

    public function canUpdateUser(User $actor): bool
    {
        if (null === $this->apId) {
            return null === $actor->apId && $actor->isAdmin();
        } else {
            return $this->apDomain === $actor->apDomain;
        }
    }

    public function getApplicationStatus(): EApplicationStatus
    {
        return EApplicationStatus::getFromString($this->applicationStatus);
    }

    public function setApplicationStatus(EApplicationStatus $applicationStatus): void
    {
        $this->applicationStatus = $applicationStatus->value;
    }

    /**
     * @param User $dmAuthor the author of the direct message
     *
     * @return bool whether the $dmAuthor is allowed to send this user a direct message
     */
    public function canReceiveDirectMessage(User $dmAuthor): bool
    {
        if (EDirectMessageSettings::Everyone->value === $this->directMessageSetting) {
            return true;
        } elseif (EDirectMessageSettings::FollowersOnly->value === $this->directMessageSetting) {
            $criteria = Criteria::create()->where(Criteria::expr()->eq('follower', $dmAuthor));

            return $this->followers->matching($criteria)->count() > 0;
        } else {
            return false;
        }
    }

    /**
     * this is used to check whether the session of a user is valid
     * if any of these values have changed the user needs to re-login
     * it should be the same values as the remember-me cookie signature in the security.yaml
     * also have a look at the isEqualTo function as this stuff needs to be checked there.
     *
     * @see User::isEqualTo()
     */
    public function __serialize(): array
    {
        return [
            "\0".self::class."\0id" => $this->id,
            "\0".self::class."\0username" => $this->username,
            "\0".self::class."\0password" => $this->password,
            "\0".self::class."\0totpSecret" => $this->totpSecret ? hash('sha256', $this->totpSecret) : '',
            "\0".self::class."\0isBanned" => $this->isBanned,
            "\0".self::class."\0isDeleted" => $this->isDeleted,
            "\0".self::class."\0markedForDeletionAt" => $this->markedForDeletionAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Traits\CreatedAtTrait;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity(repositoryClass: NotificationRepository::class)]
#[InheritanceType('SINGLE_TABLE')]
#[DiscriminatorColumn(name: 'log_type', type: 'text')]
#[DiscriminatorMap(self::DISCRIMINATOR_MAP)]
abstract class MagazineLog
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    public const DISCRIMINATOR_MAP = [
        'entry_deleted' => MagazineLogEntryDeleted::class,
        'entry_restored' => MagazineLogEntryRestored::class,
        'entry_comment_deleted' => MagazineLogEntryCommentDeleted::class,
        'entry_comment_restored' => MagazineLogEntryCommentRestored::class,
        'entry_pinned' => MagazineLogEntryPinned::class,
        'entry_unpinned' => MagazineLogEntryUnpinned::class,
        'post_deleted' => MagazineLogPostDeleted::class,
        'post_restored' => MagazineLogPostRestored::class,
        'post_comment_deleted' => MagazineLogPostCommentDeleted::class,
        'post_comment_restored' => MagazineLogPostCommentRestored::class,
        'ban' => MagazineLogBan::class,
        'moderator_add' => MagazineLogModeratorAdd::class,
        'moderator_remove' => MagazineLogModeratorRemove::class,
    ];

    public const CHOICES = [
        'entry_deleted',
        'entry_restored',
        'entry_comment_deleted',
        'entry_comment_restored',
        'entry_pinned',
        'entry_unpinned',
        'post_deleted',
        'post_restored',
        'post_comment_deleted',
        'post_comment_restored',
        'ban',
        'moderator_add',
        'moderator_remove',
    ];

    #[ManyToOne(targetEntity: Magazine::class, inversedBy: 'logs')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Magazine $magazine;
    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    /**
     * Usually the acting moderator. There are 2 exceptions MagazineLogModeratorAdd and MagazineLogModeratorRemove;
     * in that case this is the moderator being added or removed, because the acting moderator can be null.
     *
     * @see MagazineLogModeratorAdd
     * @see MagazineLogModeratorRemove
     */
    public User $user;
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    public function __construct(Magazine $magazine, User $user)
    {
        $this->magazine = $magazine;
        $this->user = $user;

        $this->createdAtTraitConstruct();
    }

    abstract public function getSubject(): ?ContentInterface;

    abstract public function clearSubject(): MagazineLog;

    abstract public function getType(): string;
}

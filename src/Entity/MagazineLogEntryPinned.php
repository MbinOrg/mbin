<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ContentInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MagazineLogEntryPinned extends MagazineLog
{
    #[ManyToOne(targetEntity: Entry::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Entry $entry = null;

    #[ManyToOne(targetEntity: User::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?User $actingUser;

    public function __construct(Magazine $magazine, ?User $actingUser, Entry $unpinnedEntry)
    {
        parent::__construct($magazine, $unpinnedEntry->user);
        $this->entry = $unpinnedEntry;
        $this->actingUser = $actingUser;
    }

    public function getSubject(): ContentInterface|null
    {
        return $this->entry;
    }

    public function clearSubject(): MagazineLog
    {
        $this->entry = null;

        return $this;
    }

    public function getType(): string
    {
        return 'log_entry_pinned';
    }
}

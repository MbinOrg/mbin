<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ContentInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MagazineLogEntryCommentPurged extends MagazineLog
{
    #[Column(type: 'string', length: Entry::MAX_TITLE_LENGTH)]
    public string $title;

    #[JoinColumn(onDelete: 'CASCADE')]
    #[ManyToOne(targetEntity: User::class)]
    public User $author;

    public function __construct(Magazine $magazine, User $user, string $title, User $author)
    {
        parent::__construct($magazine, $user);
        $this->title = $title;
        $this->author = $author;
    }

    public function getSubject(): ?ContentInterface
    {
        return null;
    }

    public function clearSubject(): MagazineLog
    {
        return $this;
    }

    public function getType(): string
    {
        return 'log_entry_comment_purged';
    }
}

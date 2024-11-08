<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[Entity]
#[UniqueConstraint(name: 'bookmark_list_entry_entryComment_post_postComment_idx', columns: ['list_id', 'entry_id', 'entry_comment_id', 'post_id', 'post_comment_id'])]
class Bookmark
{
    use CreatedAtTrait {
        CreatedAtTrait::__construct as createdAtTraitConstruct;
    }

    #[Column, Id, GeneratedValue]
    private int $id;

    #[ManyToOne(targetEntity: BookmarkList::class, inversedBy: 'entities')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BookmarkList $list;

    #[ManyToOne, JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ManyToOne, JoinColumn(onDelete: 'CASCADE')]
    public ?Entry $entry = null;

    #[ManyToOne, JoinColumn(onDelete: 'CASCADE')]
    public ?EntryComment $entryComment = null;

    #[ManyToOne, JoinColumn(onDelete: 'CASCADE')]
    public ?Post $post = null;

    #[ManyToOne, JoinColumn(onDelete: 'CASCADE')]
    public ?PostComment $postComment = null;

    public function __construct(User $user, BookmarkList $list)
    {
        $this->user = $user;
        $this->list = $list;
        $this->createdAtTraitConstruct();
    }

    public function setContent(Post|EntryComment|PostComment|Entry $content): void
    {
        if ($content instanceof Entry) {
            $this->entry = $content;
        } elseif ($content instanceof EntryComment) {
            $this->entryComment = $content;
        } elseif ($content instanceof Post) {
            $this->post = $content;
        } elseif ($content instanceof PostComment) {
            $this->postComment = $content;
        }
    }

    public function getContent(): Entry|EntryComment|Post|PostComment
    {
        return $this->entry ?? $this->entryComment ?? $this->post ?? $this->postComment;
    }
}

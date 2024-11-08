<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagLinkRepository;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity(repositoryClass: TagLinkRepository::class)]
class HashtagLink
{
    #[Id, GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[ManyToOne(targetEntity: Hashtag::class, inversedBy: 'linkedPosts')]
    #[JoinColumn(name: 'hashtag_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public Hashtag $hashtag;

    #[ManyToOne(targetEntity: Entry::class, inversedBy: 'hashtags')]
    #[JoinColumn(name: 'entry_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?Entry $entry;

    #[ManyToOne(targetEntity: EntryComment::class, inversedBy: 'hashtags')]
    #[JoinColumn(name: 'entry_comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?EntryComment $entryComment;

    #[ManyToOne(targetEntity: Post::class, inversedBy: 'hashtags')]
    #[JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?Post $post;

    #[ManyToOne(targetEntity: PostComment::class, inversedBy: 'hashtags')]
    #[JoinColumn(name: 'post_comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public ?PostComment $postComment;
}

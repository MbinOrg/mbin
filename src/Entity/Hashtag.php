<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;

#[Entity(repositoryClass: TagRepository::class)]
class Hashtag
{
    #[Id, GeneratedValue]
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'citext', unique: true)]
    public string $tag;

    #[Column(type: 'boolean', options: ['default' => false])]
    public bool $banned = false;

    #[OneToMany(mappedBy: 'hashtag', targetEntity: HashtagLink::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    public Collection $linkedPosts;
}

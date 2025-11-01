<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Contracts\ContentInterface;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

#[Entity]
class MagazineLogPostUnlocked extends MagazineLog
{
    #[ManyToOne(targetEntity: Post::class)]
    #[JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Post $post = null;

    public function __construct(Post $post, User $user)
    {
        parent::__construct($post->magazine, $user);

        $this->post = $post;
    }

    public function getSubject(): ?ContentInterface
    {
        return $this->post;
    }

    public function clearSubject(): MagazineLog
    {
        $this->post = null;

        return $this;
    }

    public function getType(): string
    {
        return 'log_post_unlocked';
    }
}

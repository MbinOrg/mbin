<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Post;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('post_inline_md')]
final class PostInlineMdComponent
{
    public Post $post;

    public bool $userFullName = false;

    public bool $magazineFullName = false;
}

<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\PostComment;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('post_comment_inline_md')]
final class PostCommentInlineComponent
{
    public PostComment $comment;
}

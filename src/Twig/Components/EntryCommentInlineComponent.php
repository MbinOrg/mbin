<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\EntryComment;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('entry_comment_inline_md')]
final class EntryCommentInlineComponent
{
    public EntryComment $comment;
}

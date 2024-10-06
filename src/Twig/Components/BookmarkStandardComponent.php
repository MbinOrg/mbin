<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('bookmark_standard')]
class BookmarkStandardComponent
{
    public Entry|EntryComment|Post|PostComment $subject;
    public string $subjectClass;
}

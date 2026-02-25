<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Pagination\Cursor\CursorPaginationInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('cursor_pagination')]
class CursorPaginationComponent
{
    public bool $isForward = true;

    public CursorPaginationInterface $pagination;
}

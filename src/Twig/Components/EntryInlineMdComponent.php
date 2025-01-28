<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Entry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('entry_inline_md')]
final class EntryInlineMdComponent
{
    public Entry $entry;

    public bool $userFullName = false;

    public bool $magazineFullName = false;
}

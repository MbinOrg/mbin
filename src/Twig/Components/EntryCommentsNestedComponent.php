<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\EntryComment;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('entry_comments_nested')]
final class EntryCommentsNestedComponent
{
    public EntryComment $comment;
    public int $level;
    public string $view = ThemeSettingsController::TREE;
}

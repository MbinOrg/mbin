<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\PostComment;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('post_comments_nested')]
final class PostCommentsNestedComponent
{
    public PostComment $comment;
    public int $level;
    public string $view = ThemeSettingsController::TREE;
}

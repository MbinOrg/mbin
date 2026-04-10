<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\PostComment;
use App\Repository\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('post_comment_combined')]
final class PostCommentCombinedComponent
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public PostComment $comment;
    public bool $dateAsUrl = true;
    public bool $showNested = false;
    public bool $withPost = false;
    public int $level = 1;
    public bool $canSeeTrash = false;
    public Criteria $criteria;

    public function postMount(array $attr): array
    {
        $this->canSeeTrashed();

        return $attr;
    }

    public function canSeeTrashed(): bool
    {
        if (VisibilityInterface::VISIBILITY_VISIBLE === $this->comment->visibility) {
            return true;
        }

        if (VisibilityInterface::VISIBILITY_TRASHED === $this->comment->visibility
            && $this->authorizationChecker->isGranted(
                'moderate',
                $this->comment
            )
            && $this->canSeeTrash) {
            return true;
        }

        $this->comment->image = null;

        return false;
    }
}

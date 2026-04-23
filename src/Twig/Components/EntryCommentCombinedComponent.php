<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Controller\User\ThemeSettingsController;
use App\Entity\Contracts\VisibilityInterface;
use App\Entity\EntryComment;
use App\PageView\EntryCommentPageView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('entry_comment_combined')]
final class EntryCommentCombinedComponent
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public EntryComment $comment;
    public bool $canSeeTrash = false;
    public EntryCommentPageView $criteria;

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

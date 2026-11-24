<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

abstract class AbstractSubjectComponent
{
    protected function __construct(
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected Entry|EntryComment|Post|PostComment $subject;

    public bool $canSeeTrash = false;

    protected function init(Entry|EntryComment|Post|PostComment $subject): void
    {
        $this->subject = $subject;
    }

    public function canSeeTrashed(): bool
    {
        if (VisibilityInterface::VISIBILITY_VISIBLE === $this->subject->visibility) {
            return true;
        }

        if (VisibilityInterface::VISIBILITY_TRASHED === $this->subject->visibility
            && $this->authorizationChecker->isGranted(
                'moderate',
                $this->subject
            )
            && $this->canSeeTrash) {
            return true;
        }

        $this->subject->image = null;

        return false;
    }
}

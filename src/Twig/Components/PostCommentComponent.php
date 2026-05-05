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

#[AsTwigComponent('post_comment')]
final class PostCommentComponent extends AbstractSubjectComponent
{
    public function __construct(
        private readonly RequestStack $requestStack,
        AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($authorizationChecker);
    }

    public PostComment $comment;
    public bool $dateAsUrl = true;
    public bool $showNested = false;
    public bool $withPost = false;
    public int $level = 1;
    public Criteria $criteria;

    public function postMount(array $attr): array
    {
        $this->init($this->comment);

        $this->canSeeTrashed();

        return $attr;
    }

    public function getLevel(): int
    {
        if (ThemeSettingsController::CLASSIC === $this->requestStack->getMainRequest()->cookies->get(
            ThemeSettingsController::POST_COMMENTS_VIEW
        )) {
            return min($this->level, 2);
        }

        return min($this->level, 10);
    }
}

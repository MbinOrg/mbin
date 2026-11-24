<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\PostComment;
use App\Repository\Criteria;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('post_comment_combined')]
final class PostCommentCombinedComponent extends AbstractSubjectComponent
{
    public function __construct(
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

    #[PostMount]
    public function postMount(array $attr): array
    {
        $this->init($this->comment);

        $this->canSeeTrashed();

        return $attr;
    }
}

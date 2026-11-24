<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\EntryComment;
use App\PageView\EntryCommentPageView;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('entry_comment_combined')]
final class EntryCommentCombinedComponent extends AbstractSubjectComponent
{
    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($authorizationChecker);
    }

    public EntryComment $comment;
    public EntryCommentPageView $criteria;

    #[PostMount]
    public function postMount(array $attr): array
    {
        $this->init($this->comment);

        $this->canSeeTrashed();

        return $attr;
    }
}

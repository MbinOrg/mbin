<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Post;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('post')]
class PostComponent extends AbstractSubjectComponent
{
    public Post $post;
    public bool $isSingle = false;
    public bool $showMagazineName = true;
    public bool $dateAsUrl = true;
    public bool $showCommentsPreview = false;
    public bool $showExpand = true;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
    ) {
        parent::__construct($authorizationChecker);
    }

    #[PostMount]
    public function postMount(array $attr): array
    {
        $this->init($this->post);

        $this->canSeeTrashed();

        if ($this->isSingle) {
            $this->showMagazineName = false;

            if (isset($attr['class'])) {
                $attr['class'] = trim('post--single '.$attr['class']);
            } else {
                $attr['class'] = 'post--single';
            }
        }

        return $attr;
    }
}

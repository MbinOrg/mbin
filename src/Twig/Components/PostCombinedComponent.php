<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('post_combined')]
class PostCombinedComponent extends PostComponent
{
    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        parent::__construct($authorizationChecker);
    }
}

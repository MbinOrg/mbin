<?php

declare(strict_types=1);

namespace App\PageView;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\Criteria;
use Symfony\Bundle\SecurityBundle\Security;

class PostCommentPageView extends Criteria
{
    public const SORT_OPTIONS = [
        self::SORT_NEW,
        self::SORT_OLD,
        self::SORT_TOP,
    ];

    public ?Post $post = null;
    public bool $onlyParents = true;

    public function __construct(
        int $page,
        private readonly Security $security,
    ) {
        parent::__construct($page);
    }

    public function resolveSort(?string $value): string
    {
        $routes = $this->routes();
        $defaultRoute = $routes['hot'];
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $defaultRoute = $user->commentDefaultSort->value;
        }

        return 'default' !== $value ? $routes[$value] : $defaultRoute;
    }
}

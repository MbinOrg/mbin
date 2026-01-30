<?php

declare(strict_types=1);

namespace App\PageView;

use App\Entity\User;
use App\Repository\Criteria;
use Symfony\Bundle\SecurityBundle\Security;

class ContentPageView extends Criteria
{
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
            $defaultRoute = $user->frontDefaultSort;
        }

        return 'default' !== $value ? $routes[$value] : $defaultRoute;
    }
}

<?php

declare(strict_types=1);

namespace App\PageView;

use App\Entity\Entry;
use App\Entity\User;
use App\Repository\Criteria;
use Symfony\Bundle\SecurityBundle\Security;

class EntryCommentPageView extends Criteria
{
    public const SORT_OPTIONS = [
        self::SORT_NEW,
        self::SORT_TOP,
        self::SORT_HOT,
        self::SORT_NEW,
        self::SORT_OLD,
    ];

    public ?Entry $entry = null;
    public bool $onlyParents = true;
    /**
     * @var int|null if null, no filter will be applied
     */
    public ?int $parent = null;

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

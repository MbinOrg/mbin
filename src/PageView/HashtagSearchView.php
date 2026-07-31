<?php

declare(strict_types=1);

namespace App\PageView;

use App\Repository\Criteria;

class HashtagSearchView extends Criteria
{
    public ?string $query = null;

    public function __construct(
        public int $page,
    ) {
        parent::__construct($page);
        $this->setContent('_search_hashtags');
    }

    protected function routes(): array
    {
        return [];
    }
}

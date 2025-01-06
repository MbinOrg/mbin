<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ApiDataProvider\DtoPaginator;
use App\Controller\AbstractController;
use App\Entity\Magazine;
use App\Factory\BadgeFactory;

class MagazineBadges extends AbstractController
{
    public function __construct(private readonly BadgeFactory $factory)
    {
    }

    /**
     * @todo DtoPaginator class does not seem to exist.
     */
    public function __invoke(Magazine $magazine)
    {
        $dtos = array_map(fn ($badge) => $this->factory->createDto($badge), $magazine->badges->toArray());

        return new DtoPaginator($dtos, 0, 10, \count($dtos));
    }
}

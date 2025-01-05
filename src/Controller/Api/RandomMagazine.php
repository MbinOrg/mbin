<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ApiDataProvider\DtoPaginator;
use App\Controller\AbstractController;
use App\Factory\MagazineFactory;
use App\Repository\MagazineRepository;

class RandomMagazine extends AbstractController
{
    public string $titleTag = 'span';

    public function __construct(
        private readonly MagazineFactory $factory,
        private readonly MagazineRepository $repository,
    ) {
    }

    /**
     * @todo DtoPaginator class does not seem to exist.
     */
    public function __invoke()
    {
        try {
            $magazine = $this->repository->findRandom();
        } catch (\Exception $e) {
            return [];
        }
        $dtos = [$this->factory->createDto($magazine)];

        return new DtoPaginator($dtos, 0, 1, 1);
    }
}

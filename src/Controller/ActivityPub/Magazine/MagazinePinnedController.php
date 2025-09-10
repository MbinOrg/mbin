<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\Magazine;

use App\Entity\Magazine;
use App\Factory\ActivityPub\CollectionFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MagazinePinnedController
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    public function __invoke(Magazine $magazine, Request $request): JsonResponse
    {
        $data = $this->collectionFactory->getMagazinePinnedCollection($magazine);
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}

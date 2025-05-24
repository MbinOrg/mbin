<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Repository\ActivityRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class ObjectController
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $uuid = Uuid::fromString($id);
        $activity = $this->activityRepository->findOneBy(['uuid' => $uuid, 'isRemote' => false]);
        if (null === $activity) {
            return new JsonResponse(status: 404);
        }

        $response = new JsonResponse($this->activityJsonBuilder->buildActivityJson($activity));
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}

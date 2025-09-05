<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\ActivityPub\ActivityJsonBuilder;
use App\Service\ActivityPub\Wrapper\CollectionInfoWrapper;
use App\Service\ActivityPub\Wrapper\CollectionItemsWrapper;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserOutboxController extends AbstractController
{
    public function __construct(
        private readonly CollectionInfoWrapper $collectionInfoWrapper,
        private readonly CollectionItemsWrapper $collectionItemsWrapper,
        private readonly ActivityJsonBuilder $activityJsonBuilder,
        private readonly ActivityRepository $activityRepository,
    ) {
    }

    public function __invoke(User $user, Request $request): JsonResponse
    {
        if ($user->apId) {
            throw $this->createNotFoundException();
        }

        if (!$request->get('page')) {
            $data = $this->getCollectionInfo($user);
        } else {
            $data = $this->getCollectionItems($user, (int) $request->get('page'));
        }

        $response = new JsonResponse($data);

        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'id' => 'string',
        'first' => 'string',
        'totalItems' => 'int',
    ])]
    private function getCollectionInfo(User $user): array
    {
        $fanta = $this->activityRepository->getOutboxActivitiesOfUser($user);

        return $this->collectionInfoWrapper->build(
            'ap_user_outbox',
            ['username' => $user->username],
            $fanta->count()
        );
    }

    #[ArrayShape([
        '@context' => 'string',
        'type' => 'string',
        'partOf' => 'string',
        'id' => 'string',
        'totalItems' => 'int',
        'orderedItems' => 'array',
    ])]
    private function getCollectionItems(
        User $user,
        int $page,
    ): array {
        $activity = $this->activityRepository->getOutboxActivitiesOfUser($user);
        $activity->setCurrentPage($page);
        $activity->setMaxPerPage(10);

        $items = [];
        foreach ($activity as $item) {
            $json = $this->activityJsonBuilder->buildActivityJson($item);
            unset($json['@context']);
            $items[] = $json;
        }

        return $this->collectionItemsWrapper->build(
            'ap_user_outbox',
            ['username' => $user->username],
            $activity,
            $items,
            $page,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Factory\ActivityPub\CollectionFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserOutboxController extends AbstractController
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    public function __invoke(User $user, Request $request): JsonResponse
    {
        if ($user->apId) {
            throw $this->createNotFoundException();
        }

        if (!$request->get('page')) {
            $data = $this->collectionFactory->getUserOutboxCollection($user);
        } else {
            $data = $this->collectionFactory->getUserOutboxCollectionItems($user, (int) $request->get('page'));
        }

        $response = new JsonResponse($data);

        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}

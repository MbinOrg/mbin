<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub\User;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Factory\ActivityPub\PersonFactory;
use App\Factory\ActivityPub\TombstoneFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class UserController extends AbstractController
{
    public function __construct(
        private readonly TombstoneFactory $tombstoneFactory,
        private readonly PersonFactory $personFactory
    ) {
    }

    public function __invoke(User $user, Request $request): JsonResponse
    {
        if ($user->apId) {
            throw $this->createNotFoundException();
        }

        if (!$user->isApproved || $user->isRejected) {
            throw $this->createNotFoundException();
        }

        if (!$user->isDeleted || null !== $user->markedForDeletionAt) {
            $response = new JsonResponse($this->personFactory->create($user, true));
        } else {
            $response = new JsonResponse($this->tombstoneFactory->createForUser($user));
            $response->setStatusCode(410);
        }

        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }
}

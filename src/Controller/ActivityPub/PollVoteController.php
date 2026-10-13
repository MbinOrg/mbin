<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Controller\AbstractController;
use App\Entity\PollVote;
use App\Entity\User;
use App\Factory\ActivityPub\PollVoteFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PollVoteController extends AbstractController
{
    public function __invoke(
        Request $request,
        PollVoteFactory $pollVoteFactory,
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] PollVote $pollVote,
    ): JsonResponse {
        if ($pollVote->getUser()->getId() !== $user->getId()) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse(
            $pollVoteFactory->build($pollVote),
            headers: ['Content-Type' => 'application/activity+json'],
        );
    }
}

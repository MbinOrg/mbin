<?php

namespace App\Controller\Api\Entry\Comments;

use App\Controller\Api\Entry\EntriesBaseApi;
use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ActivitiesResponseDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\EntryFavourite;
use App\Entity\EntryVote;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;


class EntryCommentsActivityApi extends EntriesBaseApi
{

    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'comment_id')]
        EntryComment $comment,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($comment);

        $dto = ActivitiesResponseDto::create([], [], null);
        /* @var EntryVote $upvote */
        foreach ($comment->getUpVotes() as $upvote) {
            $dto->boosts[] = $this->userFactory->createSmallDto($upvote->user);
        }
        /* @var EntryFavourite $favourite */
        foreach ($comment->favourites as $favourite) {
            $dto->upvotes[] = $this->userFactory->createSmallDto($favourite->user);
        }

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }

}

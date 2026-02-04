<?php

namespace App\Controller\Api\Entry;

use App\Controller\Traits\PrivateContentTrait;
use App\DTO\ActivitiesResponseDto;
use App\Entity\Entry;
use App\Entity\EntryFavourite;
use App\Entity\EntryVote;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class EntriesActivityApi extends EntriesBaseApi
{

    use PrivateContentTrait;

    public function __invoke(
        #[MapEntity(id: 'entry_id')]
        Entry $entry,
        RateLimiterFactory $apiReadLimiter,
        RateLimiterFactory $anonymousApiReadLimiter,
    ): JsonResponse {
        $headers = $this->rateLimit($apiReadLimiter, $anonymousApiReadLimiter);

        $this->handlePrivateContent($entry);

        $dto = ActivitiesResponseDto::create([], [], null);
        /* @var EntryVote $upvote */
        foreach ($entry->getUpVotes() as $upvote) {
            $dto->boosts[] = $this->userFactory->createSmallDto($upvote->user);
        }
        /* @var EntryFavourite $favourite */
        foreach ($entry->favourites as $favourite) {
            $dto->upvotes[] = $this->userFactory->createSmallDto($favourite->user);
        }

        return new JsonResponse(
            $dto,
            headers: $headers
        );
    }

}
